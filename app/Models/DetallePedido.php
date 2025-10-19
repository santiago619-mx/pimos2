<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedido extends Model
{
    use HasFactory;

    // Especifica el nombre de la tabla
    protected $table = 'detalles_pedidos';

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'pedido_id', 
        'producto_id', 
        'cantidad', 
        'precio_unitario',
    ];

    // Relación Inversa: La línea de detalle pertenece a un pedido.
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    // Relación Inversa: La línea de detalle pertenece a un producto.
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}