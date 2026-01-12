# Store Management System (Symfony)

A complete **store & stock management system** built with **Symfony 7**, covering inventory, suppliers, supply workflow, shop/cart, orders, reports, authentication, and dashboards with charts.

This project was built step-by-step as a full learning journey from environment setup to a production-ready structure.

---

## ✨ Features

### Core Management
- Product management (CRUD, images upload, soft delete)
- Stock tracking (in stock / low / out)
- Supplier management
- Supply requests workflow (pending / confirmed / rejected)
- Reports & exports (CSV, TXT, PDF-ready structure)

### Store & Orders
- Public shop
- Cart & checkout
- Orders & order items
- Revenue & order growth tracking

### Dashboard
- Real-time statistics
- Charts & graphs (Chart.js)
- Stock overview
- Category distribution
- Supplier activity
- Orders & financial growth
- Recent activities feed

### Security
- Authentication (login / register)
- Role-based access (ADMIN / USER)
- Protected routes & UI
- Dynamic navbar (profile & notifications)

---

## 🛠 Tech Stack

- Symfony 7
- PHP 8.2
- Doctrine ORM & Migrations
- Twig
- MySQL / MariaDB
- Chart.js (CDN)
- Bootstrap 5
- Font Awesome
- Git & GitHub

---

## 📦 Required Bundles

Installed via Composer:
- symfony/orm-pack
- symfony/maker-bundle
- symfony/security-bundle
- symfony/form
- symfony/validator
- symfony/uid
- doctrine/doctrine-migrations-bundle

---

## 🚀 Installation Guide

### 1. Clone & install
```bash
git clone https://github.com/ismailel06/store-management.git
cd store-management
composer install
