<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    use HasFactory;

    // Especifica la clave primaria (PK) personalizada
    protected $primaryKey = 'inventario_id';
    
    // Indica a Laravel que la clave primaria es auto-incrementable (por defecto)
    public $incrementing = true; 

    // Campos que pueden ser asignados masivamente (sin la PK)
    protected $fillable = [
        'producto_id',
        'cantidad_existencias',
    ];

    /**
     * Relación Inversa: El registro de inventario pertenece a un único producto.
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}