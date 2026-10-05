# Laravel Modular Lite

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hatchyu/laravel-modular-lite.svg?style=flat-square)](https://packagist.org/packages/hatchyu/laravel-modular-lite)
[![Total Downloads](https://img.shields.io/packagist/dt/hatchyu/laravel-modular-lite.svg?style=flat-square)](https://packagist.org/packages/hatchyu/laravel-modular-lite)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

A lightweight, zero-ceremony **Modular Architecture** package for Laravel applications. Designed for developers who want the organizational benefits of feature-based modules without the cognitive overhead of complex enterprise abstractions.

> [!TIP]
> **Building an Enterprise Application?**  
> If you are building large-scale, complex enterprise platforms (ERP, CRM, Banking, Multi-domain systems) requiring strict **Domain-Driven Design (DDD) 4-layer boundaries**, **CQRS (Actions/Queries/Data)**, and architectural testing, check out our flagship enterprise package:  
> 👉 **[`hatchyu/laravel-modular`](https://github.com/rajeshmk/laravel-modular)** (`composer require hatchyu/laravel-modular`)

---

## 🎯 Design Philosophy: Stay Close to Laravel

If you are building an MVP, SaaS, eCommerce store, or internal tool, you don't always need 7 architectural layers (Domain Model, Contract, Repository, DTO, Action, Request, Resource, Controller) for a simple database record.

**Laravel Modular Lite** gives you:
- 🚀 **Familiar Laravel Structure:** Put your `Models`, `Controllers`, `Requests`, `Resources`, and `Services` in clean module folders.
- ⚡ **Compiled Module Discovery:** Eliminate runtime filesystem scanning in production with compiled manifest caching (`php artisan module:cache`).
- 🛠️ **Frictionless CRUD:** Generate an entire vertical CRUD slice (`Model`, `Migration`, `Factory`, `Requests`, `Resource`, `Controller`, `Routes`, and `Tests`) in a single command.
- 🩺 **Self-Healing Diagnostics:** Built-in `php artisan module:doctor` to verify PSR-4 mappings, check route syntax, and repair missing directories automatically with `--fix`.
- 🔒 **Security Hardened:** Path traversal immunity, migration table name sanitization, and strict regex module validation.

---

## 📂 Module Structure

When you create a module with `php artisan module:make Shop`, it generates a clean, conventional directory tree:

```
modules/
└── Shop/
    ├── Controllers/
    │   └── Api/
    │       └── ShopController.php
    ├── Models/
    │   └── Product.php
    ├── Requests/
    │   ├── StoreProductRequest.php
    │   └── UpdateProductRequest.php
    ├── Resources/
    │   └── ProductResource.php
    ├── Services/
    │   └── CheckoutService.php
    ├── Database/
    │   ├── Migrations/
    │   ├── Factories/
    │   └── Seeders/
    ├── Routes/
    │   ├── web.php
    │   └── api.php
    ├── Views/
    │   └── index.blade.php
    ├── Providers/
    │   └── ShopServiceProvider.php
    └── Tests/
        └── Feature/
```

---

## 📦 Installation

Install the package via Composer:

```bash
composer require hatchyu/laravel-modular-lite
```

Add the modules PSR-4 namespace to your root `composer.json`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\": "modules/"
    }
}
```

Then regenerate Composer's autoloader:

```bash
composer dump-autoload
```

*(Optional)* Publish the configuration file:

```bash
php artisan vendor:publish --tag="modular-lite-config"
```

---

## 🚀 Usage & Artisan Commands

### 1. Module Management
```bash
# Scaffold a new module
php artisan module:make Shop

# List all discovered modules and subsystem readiness
php artisan module:list

# Safely rename a module and refactor namespaces across all files
php artisan module:rename Shop Marketplace

# Diagnose module health and auto-repair missing directories
php artisan module:doctor --fix
```

### 2. Instant CRUD Generation
Generate an entire vertical slice in one command:
```bash
php artisan module:make-crud Shop Product
# or shortcut:
php artisan module:crud Shop Product
```

This generates:
1. `Models/Product.php`
2. `Database/Migrations/xxxx_create_products_table.php`
3. `Database/Factories/ProductFactory.php`
4. `Requests/StoreProductRequest.php` & `UpdateProductRequest.php`
5. `Resources/ProductResource.php`
6. `Controllers/Api/ProductController.php` (pure Eloquent queries)
7. `Tests/Feature/ProductControllerTest.php`
8. Appends `Route::apiResource('products', ProductController::class);` to `Routes/api.php`

### 3. Granular Generators
```bash
# Model (supports -m, -c, -r, -f, -s, -a options)
php artisan module:make-model Shop Product -a

# Controller (supports --api and --resource)
php artisan module:make-controller Shop ProductController --api

# Requests & Resources
php artisan module:make-request Shop StoreProductRequest
php artisan module:make-resource Shop ProductResource

# Business Service
php artisan module:make-service Shop DiscountService

# Migrations & Seeders
php artisan module:make-migration Shop create_discounts_table
php artisan module:make-seeder Shop ProductSeeder
php artisan module:seed Shop

# Pest / PHPUnit Test
php artisan module:make-test Shop ProductTest
```

---

## ⚡ Production Optimization

In production environments, avoid scanning the filesystem on every request by compiling a discovery manifest:

```bash
php artisan module:cache
```

To clear the compiled cache:

```bash
php artisan module:clear
```

*(Integrated with native `php artisan optimize` and `php artisan optimize:clear`).*

---

## ⚖️ When to Choose Lite vs Enterprise

| Need | `laravel-modular-lite` (This Package) | `laravel-modular` (Enterprise) |
| :--- | :--- | :--- |
| **Ideal For** | Startups, SaaS, eCommerce, CRUD apps | ERP, CRM, Banking, Complex Domains |
| **Layering** | Simple folders (`Models`, `Controllers`, `Services`) | Strict 4-Layer DDD (`Domain`, `Application`, `Interface`, `Infrastructure`) |
| **Data Flow** | Direct Eloquent queries in controllers/services | CQRS (Actions, Queries, DTOs, Repository Interfaces) |
| **Guardrails** | Conventional Laravel | Architectural boundary enforcement via Pest Arch |

---

## 🧪 Testing

```bash
composer test
```

## 🎨 Code Style

```bash
composer format:check
composer format
```

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
