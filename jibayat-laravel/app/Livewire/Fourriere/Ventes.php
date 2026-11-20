<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\AuctionSale;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Ventes aux enchères — Flask fou_ventes : adjudications enregistrées, avec PV de vente. */
#[Title('Ventes — Fourrière')]
class Ventes extends AppPage
{
    public const ROUTE = 'fourriere.ventes';

    public function render()
    {
        $sales = AuctionSale::with(['impoundCase', 'auctionGroup'])->latest('sold_on')->latest('id')->get();

        return view('livewire.fourriere.ventes', ['sales' => $sales, 'total' => $sales->sum('hammer_price')]);
    }
}
