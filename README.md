# ConsultHours

Registro de horas facturables para una consultora. Los consultores capturan
las horas que trabajaron para cada cliente, y hay un resumen mensual de
horas facturables por cliente con control de acceso por rol.

Construido como ejercicio técnico con **PHP + MySQL (XAMPP)** en el backend,
**HTML/CSS/JavaScript** (sin frameworks) en el frontend, y un script de
**Python** que verifica de forma independiente que el resumen mensual que
regresa el API sea matemáticamente correcto.

> Documentación completa (arquitectura, decisiones de negocio, seguridad):
> ver [`docs/DOCUMENTACION.md`](docs/DOCUMENTACION.md) y [`NOTES.md`](NOTES.md).

---

## Stack

| Capa       | Tecnología                                            |
|------------|--------------------------------------------------------|
| Backend    | PHP 8 (PDO + MySQL), sesiones nativas de PHP           |
| Base de datos | MySQL (vía XAMPP)                                   |
| Frontend   | HTML5 + CSS3 (animaciones 3D) + JavaScript (vanilla)   |
| Verificación | Python 3 (script standalone, sin dependencias)       |

## Estructura del proyecto

```
PRUEBA TECNICA/
├── index.php                    # Punto de entrada (redirige al frontend)
├── database/
│   ├── schema.sql                # Definición de tablas MySQL
│   ├── seed_data.json             # Datos de prueba (fuente única de verdad)
│   └── seed.php                   # Puebla MySQL a partir de seed_data.json
├── backend/
│   ├── config/database.php        # Conexión PDO a MySQL
│   ├── includes/                  # auth.php, functions.php, bootstrap.php
│   └── api/
│       ├── auth/                  # login.php, logout.php, session.php
│       ├── clients.php
│       ├── consultants.php
│       ├── records.php            # listar/buscar (GET), crear (POST), borrar (DELETE)
│       └── summary.php            # resumen mensual facturable por cliente
├── frontend/
│   ├── index.html                 # SPA de una sola página
│   └── assets/
│       ├── css/style.css          # layout, componentes
│       └── css/animations.css     # animaciones y transiciones 3D
│       └── js/                    # api.js, auth.js, records.js, summary.js, main.js
├── scripts/
│   └── verify_summary.py          # verifica /api/summary.php contra seed_data.json
├── docs/DOCUMENTACION.md          # documentación técnica completa
└── NOTES.md                       # hallazgos de seguridad, decisiones, uso de IA
```

## Cómo correrlo (XAMPP)

1. **Copia el proyecto dentro de `htdocs`** (si no está ya ahí). Con XAMPP
   corriendo, este proyecto ya vive en `C:\xampp\htdocs\PRUEBA TECNICA`, así
   que solo necesitas iniciar **Apache** y **MySQL** desde el panel de
   control de XAMPP.

2. **Crear la base de datos** (una sola vez), con phpMyAdmin o por consola:
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   ```

3. **Sembrar los datos de prueba**:
   ```bash
   C:\xampp\php\php.exe database\seed.php
   ```
   Esto crea 3 usuarios, 4 clientes y 20 registros de horas de ejemplo
   (incluye el caso de traslape de horario del 6 de agosto que pide el
   ejercicio).

4. **Abrir el programa** en el navegador:
   ```
   http://localhost/PRUEBA%20TECNICA/
   ```
   (o directamente `http://localhost/PRUEBA%20TECNICA/frontend/index.html`)

5. **Usuarios de prueba** (contraseña real, no el hash — ver `database/seed_data.json`):

   | Usuario  | Contraseña  | Rol         |
   |----------|-------------|-------------|
   | `admin`  | `admin123`  | Administrador |
   | `carla`  | `carla2024` | Consultor    |
   | `miguel` | `miguel!!`  | Consultor    |

## Verificar el resumen mensual contra los datos de prueba

El ejercicio pide explícitamente **no confiar a ciegas** en el número que
regresa `/api/summary.php`. Para eso:

```bash
python scripts/verify_summary.py
```

El script calcula en Python, de forma independiente, cuántas horas
facturables debería tener cada cliente (a partir de
`database/seed_data.json`) y lo compara contra lo que realmente responde el
API, para cada consultor y para el administrador, en cada mes con datos.

## Notas

- Si tu instalación de XAMPP usa un usuario/contraseña de MySQL distinto al
  `root` sin contraseña por defecto, ajusta `backend/config/database.php`.
- El proyecto no usa Node.js, npm ni ningún framework: todo corre
  directamente sobre Apache + PHP de XAMPP, tal como se pidió.
