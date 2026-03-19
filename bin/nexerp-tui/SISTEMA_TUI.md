# Sistema TUI Homogéneo - Guía de Desarrollo

## Descripción General

El nuevo sistema TUI (Text User Interface) de nexERP está inspirado en los sistemas IBM AS/400 (5250) y mainframes IBM (3270), proporcionando una interfaz consistente y profesional con navegación estandarizada mediante teclas de función.

## Componentes Principales

### 1. Window (`Display/Window.php`)
Sistema de ventanas completas con bordes, título y barra de funciones.

**Uso básico:**
```php
$window = new Window($screen, 'TÍTULO DE LA VENTANA', 80, 24);

// Para tablas
$window->setTable($columns, $data, $selectedRow);

// Para formularios
$window->addField('nombre', 'Nombre', 'valor', required: true);

$window->render();
```

### 2. ListController (`Display/ListController.php`)
Controlador de listas/tablas con navegación por cursores y paginación automática.

**Uso:**
```php
$listController = new ListController($keyHandler, $screen, 'LISTA DE CLIENTES');

// Definir columnas
$listController->setColumns([
    ['field' => 'codigo', 'label' => 'Código', 'width' => 12],
    ['field' => 'nombre', 'label' => 'Nombre', 'width' => 35, 'format' => 'text'],
    ['field' => 'precio', 'label' => 'Precio', 'width' => 15, 'format' => 'currency'],
]);

// Callback para obtener datos (paginación automática)
$listController->onFetch(function($page, $perPage) {
    $response = $this->client->getData($page, $perPage);
    return [
        'data' => $response['data'],
        'total' => $response['total'],
        'last_page' => $response['last_page']
    ];
});

// Callbacks para acciones
$listController->onCreate(function() {
    // Crear nuevo registro
});

$listController->onEdit(function($record) {
    // Editar registro seleccionado
});

$listController->onDelete(function($record) {
    // Eliminar registro (con confirmación automática)
});

$listController->onView(function($record) {
    // Ver detalles del registro
});

// Ejecutar
$listController->run();
```

### 3. FormController (`Display/FormController.php`)
Controlador de formularios con navegación TAB/Shift+TAB y validación.

**Uso:**
```php
$form = new FormController($keyHandler, $screen, 'NUEVO CLIENTE');

// Añadir campos
$form->addField('nombre', 'Nombre', '', required: true)
     ->addField('email', 'Email', '', required: false, validator: function($value) {
         if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
             return 'Email inválido';
         }
         return null;
     })
     ->addField('telefono', 'Teléfono', '', readonly: false);

// Ejecutar formulario
$values = $form->run();

if ($values) {
    // Usuario presionó F10 (Guardar)
    // $values contiene los datos del formulario
} else {
    // Usuario presionó F12 (Cancelar)
}
```

### 4. FunctionKeyMapper (`Input/FunctionKeyMapper.php`)
Mapeo estandarizado de teclas de función y especiales.

**Uso:**
```php
$rawKey = $keyHandler->waitForKey();
$key = FunctionKeyMapper::mapKey($rawKey);

if ($key === 'F10') {
    // Guardar
} elseif ($key === 'F12') {
    // Cancelar
} elseif ($key === 'TAB') {
    // Siguiente campo
}
```

## Teclas de Función Estandarizadas

| Tecla | Función | Contexto |
|-------|---------|----------|
| **F1** | Ayuda | Todos |
| **F2** | Editar registro | Listas |
| **F5** | Crear nuevo | Listas |
| **F6** | Modificar | Listas |
| **F8** | Eliminar | Listas |
| **F10** | Guardar/Confirmar | Formularios |
| **F12** | Volver/Cancelar | Todos |
| **Enter** | Siguiente campo / Ver detalles | Formularios / Listas |
| **TAB** | Siguiente campo | Formularios |
| **Shift+TAB** | Campo anterior | Formularios |
| **↑↓** | Navegar registros | Listas |
| **Page Up/Down** | Cambiar página | Listas |
| **Ctrl+→** | Cerrar registro | Especial |
| **ESC** | Volver | Todos |

## Formatos de Datos en Columnas

El `ListController` soporta varios formatos automáticos:

- `'currency'` - Formato moneda: `1234.56 €`
- `'percentage'` - Porcentaje: `21%`
- `'date'` - Fecha: `31/12/2024`
- `'datetime'` - Fecha y hora: `31/12/2024 15:30`
- `'boolean'` - Sí/No

**Ejemplo:**
```php
$listController->setColumns([
    ['field' => 'precio', 'label' => 'Precio', 'width' => 15, 'format' => 'currency'],
    ['field' => 'iva', 'label' => 'IVA', 'width' => 8, 'format' => 'percentage'],
    ['field' => 'fecha', 'label' => 'Fecha', 'width' => 12, 'format' => 'date'],
    ['field' => 'activo', 'label' => 'Activo', 'width' => 8, 'format' => 'boolean'],
]);
```

## Validación de Formularios

Los campos pueden tener validadores personalizados:

```php
$form->addField('nif', 'NIF/CIF', '', required: true, validator: function($value) {
    if (strlen($value) < 8) {
        return 'NIF/CIF debe tener al menos 8 caracteres';
    }
    return null; // null = válido
});
```

## Mensajes al Usuario

Para mostrar mensajes de éxito/error:

```php
private function showMessage(string $message, string $type = 'info'): void
{
    $this->screen->clear();
    
    $color = match($type) {
        'success' => "\033[32m",
        'error' => "\033[31m",
        'warning' => "\033[33m",
        default => "\033[37m"
    };
    
    $icon = match($type) {
        'success' => '✓',
        'error' => '✗',
        'warning' => '⚠',
        default => 'ℹ'
    };
    
    echo "\n\n  {$color}{$icon} {$message}\033[0m\n\n";
    echo "  Presione cualquier tecla para continuar...";
    
    $this->keyHandler->waitForKey();
}
```

## Ejemplo Completo: Migración de una Acción

**Antes (código antiguo):**
```php
public function listar(): void
{
    $page = 1;
    $running = true;
    
    while ($running) {
        // Renderizado manual
        // Manejo manual de teclas
        // Paginación manual
    }
}
```

**Después (nuevo sistema):**
```php
public function listar(): void
{
    $listController = new ListController($this->keyHandler, $this->screen, 'LISTA');
    
    $listController->setColumns([
        ['field' => 'codigo', 'label' => 'Código', 'width' => 12],
        ['field' => 'nombre', 'label' => 'Nombre', 'width' => 35],
    ]);
    
    $listController->onFetch(fn($page, $perPage) => 
        $this->client->getData($page, $perPage)
    );
    
    $listController->onCreate(fn() => $this->crear());
    $listController->onEdit(fn($item) => $this->editar($item['id']));
    
    $listController->run();
}
```

## Ventajas del Nuevo Sistema

1. **Consistencia**: Todas las pantallas se ven y funcionan igual
2. **Menos código**: Los controladores manejan la navegación automáticamente
3. **Mantenibilidad**: Cambios en el comportamiento se hacen en un solo lugar
4. **Familiaridad**: Los usuarios de AS/400 se sentirán como en casa
5. **Profesionalidad**: Interfaz limpia y estandarizada

## Próximos Pasos

Para migrar otras acciones al nuevo sistema:

1. Identificar si es una lista o un formulario
2. Usar `ListController` o `FormController` según corresponda
3. Definir columnas/campos
4. Implementar callbacks para acciones
5. Eliminar código de renderizado manual
6. Probar navegación con teclas de función

## Notas Importantes

- **No mezclar** el sistema antiguo con el nuevo en la misma pantalla
- **Siempre** usar `FunctionKeyMapper` para interpretar teclas
- **Validar** datos en los formularios antes de guardar
- **Manejar excepciones** y mostrar mensajes claros al usuario
- **Mantener** el ancho de ventana en 80 caracteres para compatibilidad
