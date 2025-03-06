<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteBulkAction;
use Livewire\Attributes\Reactive;
use Filament\Forms\Get;
use PhpParser\Node\Stmt\Label;
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Sales';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
        ->schema([
            Forms\Components\TextInput::make('customer_name')->required()->default('Generic')->label('Customer Name')->translateLabel(),
            Forms\Components\DatePicker::make('invoice_date')->required()->default(today()),
            Forms\Components\TextInput::make('total_amount')->numeric()->disabled()->live()->reactive() ->dehydrated(),
            Forms\Components\HasManyRepeater::make('invoiceItems')
                ->relationship('invoiceItems')
                ->schema([ Forms\Components\Select::make('product_id')
                ->label('Item')
                ->options(Product::all()->pluck('sku', 'id'))
                ->required()
                ->reactive()
                ->disableOptionWhen(function ($value, $state, Get $get) {
                    // Get all invoice items from the repeater.
                    $invoiceItems = $get('../../invoiceItems');

                    // Filter out the current row's product ID to avoid self-disabling.
                    $selectedProductIds = collect($invoiceItems)
                        ->filter(function ($item) use ($state) {
                            return $item['product_id'] !== $state;
                        })
                        ->pluck('product_id')
                        ->filter(); // Remove nulls

                    // Check if the option is already selected in another row.
                    return $selectedProductIds->contains($value);
                })



                ->afterStateUpdated(function ($state, callable $set) {
                    $item = product::find($state);
                    if ($item) {
                        $set('unit_price', $item->price);
                        $set('itemname', $item->name);
                        // Set price
                    }
                }),
                
                Forms\Components\TextInput::make('quantity')
                ->integer()
                ->required()
                ->minValue(1)
                ->live()
                ->rules([
                    function ($get) {
                        return function (string $attribute, $value, $fail) use ($get) {
                            $itemId = $get('product_id');
                            $item = product::find($itemId);
                            if ($item && $value > $item->stock) {
                                $fail("Insufficient stock. Only {$item->stock} available.");
                            }
                        };
                    },
                ])
                
                ->afterStateUpdated(function ($state, $component, $get, $set) {
                    // Calculate total when items change
                    $unitPrice = (float) $get('unit_price');
                    $quantity = (float) $get('quantity');
                
                    $set('subtotal', $unitPrice * $quantity);
                  
                }),
                
              
                Forms\Components\TextInput::make('itemname')
                ->required()
                ->disabled()
                ->reactive()
                ->dehydrated()
                ->live(),
                Forms\Components\TextInput::make('unit_price')
                ->numeric()
                ->required()
                ->disabled()
                ->dehydrated() // Save to the database
                ->live(),
                Forms\Components\TextInput::make('subtotal')
                ->numeric()
                ->required()
                ->disabled()
                
                ->dehydrated() // Save to the database
                ->reactive(),
        ])// the invoice items group ends here
        
        ->columns(5)
       
        ->afterStateUpdated(function ($state, $component, $get, $set) {
            // Calculate total when items change
            $items = $get('invoiceItems');
           
            $total = collect($items)->sum(fn ($item) =>(float) $item['quantity'] * (float) $item['unit_price']);
            $set('total_amount',  $total);

          
        }),
        

    // Total Amount (calculated dynamically)
    
    ]);


}
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->defaultSort('created_at', 'desc') // Change to sort by created_at descending
            ->columns([
            Tables\Columns\TextColumn::make('id')->label('#') ->searchable(),
            Tables\Columns\TextColumn::make('customer_name') ->searchable(),
            Tables\Columns\TextColumn::make('invoice_date')->date(),
            Tables\Columns\TextColumn::make('total_amount')->money('NIO'),

        ])
       
     
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
      
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
   
    // Invoice Resource Helpers
  /*   private static function getInvoiceItemSchema(): array
    {
        return [
            Forms\Components\Select::make('product_id')
                ->relationship('product', 'name')
                ->required()
                ->reactive()
                ->afterStateUpdated(function (\Filament\Forms\Set $set, $state, \Filament\Forms\Get $get) {
                    self::updateInvoiceItem($state, $set, $get);
                }),
            Forms\Components\TextInput::make('quantity')
                ->numeric()
                ->required()
                ->reactive(),
                
            Forms\Components\TextInput::make('unit_price')
                ->numeric()
                ->required()
                ->disabled(),
            Forms\Components\TextInput::make('subtotal')
                ->numeric()
                ->required()
                ->disabled(),
        ];
    }

    private static function updateInvoiceItem($state, \Filament\Forms\Set $set, \Filament\Forms\Get $get): void
    {
        if ($state) {
            $product = \App\Models\Product::find($state);
            if ($product) {
                $set('unit_price', $product->price);
               
                self::updateInvoiceItemSubtotal($get('quantity'), $set, $get);
            }
        } else {
            $set('unit_price', 0);
            $set('quantity', 0);
            $set('subtotal', 0);
        }
    }

    private static function updateInvoiceItemSubtotal($state, \Filament\Forms\Set $set, \Filament\Forms\Get $get): void
    {
        $unitPrice = $get('unit_price');
        if($unitPrice !== null && is_numeric($state)){
            $set('subtotal', $state * $unitPrice);
        } else {
            $set('subtotal', 0);
        }
    }

    private static function calculateInvoiceTotal($state, \Filament\Forms\Set $set): void
    {
        $total = 0;
        if (is_array($state)) {
            foreach ($state as $item) {
                if (is_array($item) && isset($item['subtotal']) && is_numeric($item['subtotal'])) {
                    $total += $item['subtotal'];
                } else {
                    // Debugging: Log or output an error message if subtotal is missing or invalid
                    \Illuminate\Support\Facades\Log::error('Invalid invoice item subtotal: ' . print_r($item, true));
                }
            }
        } else {
            // Debugging: Log an error if $state is not an array
            \Illuminate\Support\Facades\Log::error('Invoice items state is not an array: ' . print_r($state, true));
        }
        $set('total_amount', $total);
    } */
}