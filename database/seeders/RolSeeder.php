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
        // Limpiar caché de permisos (si es necesario)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. DEFINICIÓN DE ROLES
        $administrador = Role::firstOrCreate(['name' => 'Administrador']);
        $editor = Role::firstOrCreate(['name' => 'Editor']);
        $usuario = Role::firstOrCreate(['name' => 'Usuario']);

        // 2. DEFINICIÓN DE PERMISOS
        // NOTA IMPORTANTE: Se elimina 'description' de firstOrCreate para evitar el error de columna inexistente.
        // Los permisos se identifican solo por 'name' y 'guard_name'.

        // --- Permisos de Productos (CRUD) ---
        // description: Visualizar lista y detalle de productos
        Permission::firstOrCreate(['name' => 'productos.ver'])
             ->syncRoles([$administrador, $editor, $usuario]);
             
        // description: Crear nuevos productos
        Permission::firstOrCreate(['name' => 'productos.crear'])
            ->syncRoles([$administrador]);
            
        // description: Modificar productos existentes
        Permission::firstOrCreate(['name' => 'productos.editar'])
            ->syncRoles([$administrador, $editor]);
            
        // description: Eliminar productos
        Permission::firstOrCreate(['name' => 'productos.eliminar'])
            ->syncRoles([$administrador]);

        // --- Permisos de Inventario (CRUD Completo y Ajuste de Stock) ---
        // description: Visualizar el inventario y stock actual
        Permission::firstOrCreate(['name' => 'inventario.ver'])
            ->syncRoles([$administrador, $editor]);
        
        // description: Crear un nuevo registro de stock inicial para un producto
        Permission::firstOrCreate(['name' => 'inventario.crear'])
            ->syncRoles([$administrador, $editor]);

        // description: Ajustar, reabastecer o modificar la cantidad de stock
        Permission::firstOrCreate(['name' => 'inventario.ajustar_stock'])
            ->syncRoles([$administrador, $editor]);

        // description: Eliminar registros de inventario (Solo por emergencia y a discreción del Admin)
        Permission::firstOrCreate(['name' => 'inventario.eliminar_registro'])
            ->syncRoles([$administrador]);
        
        // --- Permisos de Pedidos ---
        // description: Visualizar lista completa de pedidos (Admin/Editor)
        Permission::firstOrCreate(['name' => 'pedidos.ver'])
            ->syncRoles([$administrador, $editor]); 
            
        // description: Crear nuevos pedidos
        Permission::firstOrCreate(['name' => 'pedidos.crear'])
            ->syncRoles([$administrador, $editor, $usuario]);
            
        // description: Cambiar estado de pedidos (Ej: Pendiente -> Enviado)
        Permission::firstOrCreate(['name' => 'pedidos.procesar'])
            ->syncRoles([$administrador, $editor]);
            
        // description: Eliminar o anular pedidos
        Permission::firstOrCreate(['name' => 'pedidos.cancelar'])
            ->syncRoles([$administrador]);
    }
}