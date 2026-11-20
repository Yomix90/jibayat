<?php

namespace App\Support;

use App\Models\AppModule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Lecture du registre config/navigation.php : espaces, sections, pages,
 * filtrage par permission, compteurs et recherche.
 */
class Navigation
{
    /** @var array<string, array> */
    protected array $spaces;

    public function __construct(?array $config = null)
    {
        $this->spaces = $this->normalize($config ?? config('navigation', []));
    }

    /**
     * Tous les espaces (non filtrés), avec pages enrichies (route, url, espace…).
     */
    public function all(): Collection
    {
        return collect($this->spaces);
    }

    /**
     * Espaces visibles par l'utilisateur : sections et pages filtrées, espaces vides retirés.
     */
    public function forUser(?User $user): Collection
    {
        return $this->all()
            ->map(function (array $space) use ($user) {
                $space['sections'] = collect($space['sections'])
                    ->map(function (array $section) use ($user) {
                        $section['pages'] = collect($section['pages'])
                            ->filter(fn (array $page) => $this->canSee($user, $page))
                            ->all();

                        return $section;
                    })
                    ->filter(fn (array $section) => count($section['pages']) > 0)
                    ->all();
                $space['page_count'] = collect($space['sections'])->sum(fn ($s) => count($s['pages']));

                return $space;
            })
            ->filter(fn (array $space) => $space['page_count'] > 0);
    }

    public function space(string $key): ?array
    {
        return $this->spaces[$key] ?? null;
    }

    /** Groupes du menu latéral, dans l'ordre d'affichage. */
    public const GROUPS = ['modules' => 'Modules fiscaux', 'regie' => 'Régie & documents', 'configuration' => 'Configuration'];

    /** Espaces visibles regroupés pour le menu : [groupe => [titre, espaces]]. */
    public function groupedForUser(?User $user): Collection
    {
        $spaces = $this->forUser($user);

        return collect(self::GROUPS)->map(fn ($title, $key) => ['key' => $key, 'title' => $title, 'spaces' => $spaces->where('group', $key)->values()])
            ->filter(fn ($g) => $g['spaces']->isNotEmpty())->values();
    }

    /**
     * Page d'entrée d'un espace pour l'utilisateur : son tableau de bord s'il y a accès, sinon la première page visible.
     */
    public function entryRoute(?User $user, string $key): ?string
    {
        $space = $this->forUser($user)->get($key);
        if (! $space) {
            return null;
        }
        $pages = collect($space['sections'])->flatMap(fn ($s) => $s['pages']);

        return ($space['dashboard_route'] && $pages->has($space['dashboard_route'])) ? $space['dashboard_route'] : $pages->keys()->first();
    }

    /**
     * Toutes les pages à plat (clé = nom de route).
     */
    public function pages(): Collection
    {
        return $this->all()->flatMap(
            fn (array $space) => collect($space['sections'])->flatMap(fn (array $section) => $section['pages'])
        );
    }

    public function page(string $routeName): ?array
    {
        return $this->pages()->get($routeName);
    }

    public function pagesForUser(?User $user): Collection
    {
        return $this->pages()->filter(fn (array $page) => $this->canSee($user, $page));
    }

    public function canSee(?User $user, array $page): bool
    {
        if (! self::isModuleActive($page['route'])) {
            return false;
        }
        if (empty($page['permission'])) {
            return $user !== null;
        }

        return $user !== null && $user->can($page['permission']);
    }

    /** Module (TNB, REGIE…) dont dépend une page, d'après config/jibayat.php ; null si toujours active. */
    public static function moduleFor(string $routeName): ?string
    {
        return config("jibayat.page_modules.{$routeName}")
            ?? config('jibayat.space_modules.'.explode('.', $routeName)[0]);
    }

    public static function isModuleActive(string $routeName): bool
    {
        $module = self::moduleFor($routeName);

        return $module === null || ! in_array($module, self::inactiveModules(), true);
    }

    /** @return array<string> codes des modules désactivés (mis en cache, vidé à chaque modification). */
    public static function inactiveModules(): array
    {
        try {
            return Cache::rememberForever('jibayat.inactive_modules',
                fn () => AppModule::where('is_active', false)->pluck('code')->all());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Recherche instantanée : titre, sous-titre, mots-clés (FR/AR), espace et section.
     * Insensible à la casse et aux accents. Les correspondances sur le titre passent devant.
     */
    public function search(?User $user, string $query, int $limit = 12): Collection
    {
        $terms = collect(preg_split('/\s+/u', self::fold($query)))->filter();
        if ($terms->isEmpty()) {
            return collect();
        }

        return $this->pagesForUser($user)
            ->map(function (array $page) use ($terms) {
                $title = self::fold($page['title']);
                $haystack = self::fold(implode(' ', [
                    $page['title'], $page['subtitle'] ?? '', implode(' ', $page['keywords'] ?? []),
                    $page['space_title'], $page['space_title_ar'] ?? '', $page['section_title'],
                ]));
                if (! $terms->every(fn ($t) => str_contains($haystack, $t))) {
                    return null;
                }
                $score = $terms->sum(fn ($t) => str_contains($title, $t) ? (str_starts_with($title, $t) ? 3 : 2) : 1);

                return $page + ['score' => $score];
            })
            ->filter()
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Minuscules + suppression des accents latins et des diacritiques arabes (tashkil).
     */
    public static function fold(string $text): string
    {
        $text = Str::lower(self::stripLatinAccents($text));
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        return trim($text);
    }

    protected static function stripLatinAccents(string $text): string
    {
        // Conserve les caractères arabes, ne translittère que l'alphabet latin accentué.
        return preg_replace_callback('/[\p{Latin}]+/u', fn ($m) => Str::ascii($m[0]), $text);
    }

    protected function normalize(array $config): array
    {
        $spaces = [];
        foreach ($config as $spaceKey => $space) {
            $space['key'] = $spaceKey;
            $space['group'] ??= 'modules';
            $space['route'] = 'espace';
            $space['url_params'] = ['space' => $spaceKey];
            $space['dashboard_route'] = isset($space['sections']) && collect($space['sections'])->contains(fn ($s) => isset($s['pages']['tableau-de-bord']))
                ? "{$spaceKey}.tableau-de-bord" : null;
            $sections = [];
            foreach ($space['sections'] ?? [] as $sectionKey => $section) {
                $section['key'] = $sectionKey;
                $pages = [];
                foreach ($section['pages'] ?? [] as $pageKey => $page) {
                    $routeName = "{$spaceKey}.{$pageKey}";
                    $pages[$routeName] = $page + [
                        'key' => $pageKey,
                        'route' => $routeName,
                        'uri' => "{$spaceKey}/{$pageKey}",
                        'space' => $spaceKey,
                        'space_title' => $space['title'],
                        'space_title_ar' => $space['title_ar'] ?? null,
                        'section' => $sectionKey,
                        'section_title' => $section['title'],
                        'colors' => $space['colors'],
                        'subtitle' => $page['subtitle'] ?? null,
                        'keywords' => $page['keywords'] ?? [],
                        'permission' => $page['permission'] ?? null,
                        'flask' => $page['flask'] ?? null,
                    ];
                }
                $section['pages'] = $pages;
                $sections[$sectionKey] = $section;
            }
            $space['sections'] = $sections;
            $space['page_count'] = collect($sections)->sum(fn ($s) => count($s['pages']));
            $spaces[$spaceKey] = $space;
        }

        return $spaces;
    }
}
