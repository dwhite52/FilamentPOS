<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\StockService;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = ['invoice_id','customer_name', 'invoice_date', 'total_amount'];

    public function invoiceItems() { return $this->hasMany(InvoiceItem::class); }

    protected static function booted()
    {
       
    }

}

