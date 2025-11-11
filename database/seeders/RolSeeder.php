<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role; 
use Spatie\Permission\Models\Permission; 

class RolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. DEFINICIÓN DE ROLES
        $administrador = Role::firstOrCreate(['name' => 'Administrador']);
        $editor = Role::firstOrCreate(['name' => 'Editor']);
        $usuario = Role::firstOrCreate(['name' => 'Usuario']);

        // 2. DEFINICIÓN DE PERMISOS

        // --- Permisos de Productos (CRUD) ---
        Permission::firstOrCreate(['name' => 'productos.ver', 'description' => 'Visualizar lista y detalle de productos'])
            ->syncRoles([$administrador, $editor, $usuario]);
        Permission::firstOrCreate(['name' => 'productos.crear', 'description' => 'Crear nuevos productos'])
            ->syncRoles([$administrador]);
        Permission::firstOrCreate(['name' => 'productos.editar', 'description' => 'Modificar productos existentes'])
            ->syncRoles([$administrador, $editor]);
        Permission::firstOrCreate(['name' => 'productos.eliminar', 'description' => 'Eliminar productos'])
            ->syncRoles([$administrador]);

        // --- Permisos de Inventario (CRUD Completo y Ajuste de Stock) ---
        Permission::firstOrCreate(['name' => 'inventario.ver', 'description' => 'Visualizar el inventario y stock actual'])
            ->syncRoles([$administrador, $editor]);
        
        // **NUEVO PERMISO AÑADIDO: CREAR**
        Permission::firstOrCreate(['name' => 'inventario.crear', 'description' => 'Crear un nuevo registro de stock inicial para un producto'])
            ->syncRoles([$administrador, $editor]);

        // Ajustar stock (equivalente a "editar" la cantidad)
        Permission::firstOrCreate(['name' => 'inventario.ajustar_stock', 'description' => 'Ajustar, reabastecer o modificar la cantidad de stock'])
            ->syncRoles([$administrador, $editor]);

        // Eliminación del registro (muy restringido)
        Permission::firstOrCreate(['name' => 'inventario.eliminar_registro', 'description' => 'Eliminar registros de inventario (Solo por emergencia y a discreción del Admin)'])
            ->syncRoles([$administrador]);
        
        // --- Permisos de Pedidos ---
        Permission::firstOrCreate(['name' => 'pedidos.ver', 'description' => 'Visualizar lista completa de pedidos (Admin/Editor)'])
            ->syncRoles([$administrador, $editor]); 
        Permission::firstOrCreate(['name' => 'pedidos.crear', 'description' => 'Crear nuevos pedidos'])
            ->syncRoles([$administrador, $editor, $usuario]);
        Permission::firstOrCreate(['name' => 'pedidos.procesar', 'description' => 'Cambiar estado de pedidos (Ej: Pendiente -> Enviado)'])
            ->syncRoles([$administrador, $editor]);
        Permission::firstOrCreate(['name' => 'pedidos.cancelar', 'description' => 'Eliminar o anular pedidos'])
            ->syncRoles([$administrador]);
    }
}