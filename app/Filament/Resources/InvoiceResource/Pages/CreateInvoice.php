<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Product;


class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function afterCreate(): void
    {
        // Decrement stock for each item in the invoice
        foreach ($this->record->invoiceItems as $item) {
            Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
        }
    }
}
