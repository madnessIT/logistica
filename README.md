# Sistema de Cotizaciones — Grupo Logístico GEMZ Bolivia SRL

Sistema web completo de cotizaciones para importación marítima LCL con Control de Acceso Basado en Roles (RBAC).

---

## Stack Tecnológico

| Capa       | Tecnología                                      |
|------------|-------------------------------------------------|
| Frontend   | HTML5 + Bootstrap 5.3 + JavaScript (Fetch API)  |
| Backend    | PHP 8.0+ (OOP, `strict_types=1`, sesiones seguras) |
| Base de datos | MySQL 8.0+ (relacional, llaves foráneas)     |

---

## Estructura del Proyecto

```
gemz_sistema/
│
├── config/
│   ├── config.php          # Constantes globales, TC, parámetros logísticos
│   └── Database.php        # Singleton PDO
│
├── classes/
│   ├── Auth.php            # Middleware RBAC: login, sesión, roles, CSRF
│   └── MotorCotizacion.php # Motor de cálculo LCL con todas las reglas logísticas
│
├── controllers/
│   ├── CotizacionController.php  # CRUD cotizaciones con control de acceso por rol
│   └── TarifarioController.php   # CRUD tarifas (solo Admin)
│
├── sql/
│   └── 01_schema.sql       # Esquema MySQL completo con datos de ejemplo
│
├── login.php               # Pantalla de autenticación
├── logout.php              # Cierre de sesión seguro
├── dashboard.php           # Panel principal adaptativo por rol
├── formulario.php          # Formulario de nueva cotización (Admin/Vendedor)
├── tarifario.php           # Gestión de tarifas (solo Admin)
├── pdf_cotizacion.php      # Vista de impresión optimizada para PDF
├── procesar_cotizacion.php # API endpoint JSON (calcular / guardar)
└── api_estado.php          # API endpoint para actualizar estados (Operador)
```

---

## Instalación

### 1. Requisitos del servidor
- PHP 8.0+ con extensiones: `pdo_mysql`, `session`, `openssl`
- MySQL 8.0+ o MariaDB 10.5+
- Apache 2.4+ o Nginx con módulo de reescritura

### 2. Base de datos
```bash
mysql -u root -p < sql/01_schema.sql
```

### 3. Configuración
Editar `config/config.php` con tus credenciales:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gemz_cotizaciones');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password_segura');
define('TC_USD_BS', 6.96);   // Actualizar mensualmente
```

### 4. Usuarios de ejemplo
Todos usan la contraseña: **`Gemz2025!`**

| Email                   | Rol           | Acceso                                              |
|-------------------------|---------------|-----------------------------------------------------|
| admin@gemz.com.bo       | Administrador | Todo el sistema                                     |
| deyanira@gemz.com.bo    | Vendedor      | Cotizaciones propias, clientes asignados            |
| operador@gemz.com.bo    | Operador      | Seguimiento logístico, actualización de estados     |
| sergio@cliente.com      | Cliente       | Portal propio: sus cotizaciones y estados           |

> Para cambiar contraseñas, usar `password_hash('NuevaPass', PASSWORD_BCRYPT, ['cost'=>12])` en PHP.

---

## Roles y Permisos

| Función                    | Admin | Vendedor | Operador | Cliente |
|----------------------------|:-----:|:--------:|:--------:|:-------:|
| Ver todas las cotizaciones | ✅    | ❌        | ✅ (activas) | ❌   |
| Crear cotizaciones         | ✅    | ✅        | ❌        | ❌      |
| Ver sus cotizaciones       | ✅    | ✅        | ✅        | ✅      |
| Gestionar usuarios         | ✅    | ❌        | ❌        | ❌      |
| Modificar tarifario        | ✅    | ❌        | ❌        | ❌      |
| Actualizar estados         | ✅    | ❌        | ✅        | ❌      |
| Descargar PDF              | ✅    | ✅        | ✅        | ✅      |

---

## Reglas de Negocio Implementadas

### Motor de Cálculo (MotorCotizacion.php)
1. **Regla W/M** — Se usa el mayor valor entre `Peso (kg)/1000` y `Volumen (m³)`
2. **Política de divisas** — USD: Flete Marítimo, Collect Fee, Desconsolidación, Cargos Origen · BS: resto
3. **Mínimos** — Carga ≤ 2,500 kg o ≤ 2.9 m³ → tarifa mínima de desconsolidación
4. **Extra largo** — > 12 pies → USD 12/pie adicional
5. **OWS Sobrepeso** — > 5,000 lbs/bulto → USD 170 fijo + USD 300 forklift
6. **High Cube** — Altura > 2.40 m → alerta con recargo
7. **Sin marcas de origen** → USD 300 aclaración al manifiesto
8. **Ley 20001 Chile** — Carga > 25 kg sin paletizar → alerta paletizaje
9. **Inbond Fee** — Solo origen Canadá → USD 55
10. **SED** — Valor mercadería > USD 2,500 (origen USA/Canadá) → USD 50/factura
11. **Filtro IMO** — Clases 1, 5, 6, 7 bloqueadas; Clase 3 con flash point < -18°C rechazada
12. **Sidemar** — Penalizaciones por transmisión errónea (USD 100) o fuera de plazo (USD 50)

---

## Seguridad Implementada

- **Sesiones seguras**: `httponly`, `samesite=Strict`, regeneración de ID en login
- **RBAC estricto**: verificación de rol en cada endpoint y vista
- **Protección CSRF**: token verificado en todos los formularios POST
- **Prepared Statements PDO**: prevención de SQL Injection
- **Hash seguro**: `password_hash()` con bcrypt cost=12
- **Validación tipada**: `declare(strict_types=1)` en todos los archivos PHP
- **Expiración de sesión**: 2 horas de inactividad con logout automático

---

## Personalización

### Actualizar tipo de cambio
```php
// config/config.php
define('TC_USD_BS', 6.97); // Nuevo TC
```

### Actualizar tarifas
Ingresar como Administrador → **💲 Tarifario Base** → Editar cualquier tarifa.
Los cambios se reflejan inmediatamente en nuevas cotizaciones.

### Agregar nuevos destinos
Modificar los `<select>` en `formulario.php` y agregar las rutas correspondientes en `tarifario_base` vía el panel de administración.

---

## Formato de Cotización
`COT.GEMZ/AA-XXXX` donde:
- `AA` = Últimos dos dígitos del año
- `XXXX` = Número secuencial del año (reinicia cada enero)

Ejemplo: `COT.GEMZ/25-0115`
