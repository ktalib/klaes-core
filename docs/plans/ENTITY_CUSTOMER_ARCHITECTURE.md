# Entity-Customer System - Architecture & Visual Guide

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      HTTP REQUESTS                              │
│                                                                 │
│  /entities  /customers  /api/entity-customer/*               │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│            EntityCustomerController (30+ Endpoints)            │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Entity Mgmt │ Customer Mgmt │ Advanced Operations (AJAX) │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│              EntityService (15+ Business Methods)               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Entity Matching │ CRUD Operations │ Media Management    │  │
│  │ Transaction Support │ Error Handling │ Validation       │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────┬──────────────────────────────────────┐
│                          │                                      │
▼                          ▼                                      ▼
┌──────────────────┐  ┌────────────────────┐  ┌─────────────────┐
│  Entity Model    │  │  Customer Model    │  │  Storage Layer  │
│  (Eloquent ORM)  │  │  (Eloquent ORM)    │  │  (Media Files)  │
│                  │  │                    │  │                 │
│ • entity_name    │  │ • customer_name    │  │ • Passports     │
│ • entity_type    │  │ • entity_id (FK)   │  │ • Logos         │
│ • passport_photo │  │ • email            │  │ • Validation    │
│ • company_logo   │  │ • phone            │  │                 │
│ • relationships  │  │ • relationships    │  │                 │
└──────────────────┘  └────────────────────┘  └─────────────────┘
        │                      │
        │                      │
        └──────────┬───────────┘
                   │
                   ▼
        ┌──────────────────────┐
        │   SQL Server (SQLSRV)│
        │                      │
        │  entities table      │
        │  customers table (FK)│
        │  indexes             │
        │  constraints         │
        └──────────────────────┘
```

---

## 📊 Entity-Customer Relationship Diagram

```
                    ┌─────────────────────┐
                    │     ENTITIES        │
                    │ (Unique Owners)     │
                    ├─────────────────────┤
                    │ id (PK)             │
                    │ entity_type         │
                    │ entity_name         │
                    │ passport_photo      │
                    │ company_logo        │
                    │ created_at          │
                    │ updated_at          │
                    └──────────┬──────────┘
                               │
                    ┌──────────┴──────────┐
                    │   1-to-Many (FK)    │
                    │ CASCADE UPDATE      │
                    │ RESTRICT DELETE     │
                    └──────────┬──────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
    ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
    │  CUSTOMER #1     │ │  CUSTOMER #2     │ │  CUSTOMER #n     │
    │                  │ │                  │ │                  │
    │ id: 1            │ │ id: 2            │ │ id: n            │
    │ entity_id: 5 ┼───┼─┼─ entity_id: 5 ┼─┼─ entity_id: 5    │
    │ name: A      │   │ │ name: B      │   │ │ name: C          │
    │ email: a@    │   │ │ email: b@    │   │ │ email: c@        │
    │ phone: 123   │   │ │ phone: 456   │   │ │ phone: 789       │
    └──────────────────┘ └──────────────────┘ └──────────────────┘

Example: Entity "Olakunle Company" (entity_id: 5) owns 3 properties
via 3 separate customer records
```

---

## 🔄 Entity Matching Algorithm

```
User Input: Customer Name = "Olakunle Olaniyan"
                           │
                           ▼
        ┌──────────────────────────────────┐
        │  Normalize Name                  │
        │  - Trim whitespace               │
        │  - Convert to lowercase          │
        │  Result: "olakunle olaniyan"     │
        └──────────────┬───────────────────┘
                       │
                       ▼
        ┌──────────────────────────────────┐
        │  Search entities table           │
        │  WHERE entity_name = normalized  │
        └──────────────┬───────────────────┘
                       │
            ┌──────────┴──────────┐
            │                     │
            ▼                     ▼
    ┌──────────────────┐  ┌──────────────────┐
    │   EXACT MATCH    │  │  NO MATCH        │
    │   FOUND ✓        │  │  CONTINUE ↓      │
    │                  │  │                  │
    │ Use existing     │  │ Check for SIMILAR
    │ entity_id        │  │ entities using   │
    │                  │  │ similar_text()   │
    └──────────────────┘  │ (60% threshold)  │
                          └────────┬─────────┘
                                   │
                          ┌────────┴────────┐
                          │                 │
                          ▼                 ▼
                   ┌────────────────┐  ┌────────────────┐
                   │ SIMILAR FOUND  │  │ NO SIMILAR     │
                   │                │  │                │
                   │ Show to user:  │  │ Create NEW     │
                   │ "Did you mean: │  │ entity record  │
                   │  • Olaniyan OK"│  │                │
                   │  • OK Olaniyan"│  │                │
                   └────────────────┘  └────────────────┘
                          │                     │
                          └─────────┬───────────┘
                                    │
                                    ▼
                          ┌──────────────────┐
                          │  Link Customer   │
                          │  to Entity ID    │
                          │  via FK          │
                          │  ✓ COMPLETE      │
                          └──────────────────┘
```

---

## 📁 File Structure

```
KLAES GIS EDMS/
│
├── app/
│   ├── Models/
│   │   ├── Entity.php                    ← NEW (Entity model)
│   │   └── Customer.php                  ← UPDATED (added entity_id)
│   │
│   ├── Services/
│   │   └── EntityService.php             ← NEW (business logic)
│   │
│   └── Http/
│       └── Controllers/
│           └── EntityCustomerController.php ← NEW (30+ endpoints)
│
├── database/
│   └── migrations/
│       ├── 2025_11_11_000001_create_entities_table.php
│       └── 2025_11_11_000002_add_entity_id_to_customers_table.php
│
├── routes/
│   └── apps3.php                        ← ADD routes here
│
├── storage/
│   └── app/public/uploads/
│       ├── passports/
│       │   └── entity_{id}/
│       │       └── *.jpg
│       └── logos/
│           └── entity_{id}/
│               └── *.jpg
│
└── Documentation/
    ├── ENTITY_CUSTOMER_IMPLEMENTATION.md    ← Comprehensive guide
    ├── ENTITY_CUSTOMER_DEPLOYMENT_SUMMARY.md ← Quick reference
    └── test_entity_customer_system.html     ← Testing guide
```

---

## 🔌 API Endpoint Map

```
┌─ ENTITY ENDPOINTS ─────────────────────────────────────────────┐
│                                                                 │
│  GET    /entities                  → List all entities         │
│  POST   /entities                  → Create entity             │
│  GET    /entities/{id}             → View entity               │
│  PUT    /entities/{id}             → Update entity             │
│  DELETE /entities/{id}             → Delete entity             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─ CUSTOMER ENDPOINTS ───────────────────────────────────────────┐
│                                                                 │
│  GET    /customers                 → List customers            │
│  POST   /customers                 → Create customer           │
│  GET    /customers/{id}            → View customer             │
│  PUT    /customers/{id}            → Update customer           │
│  DELETE /customers/{id}            → Delete customer           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─ ADVANCED AJAX ENDPOINTS ──────────────────────────────────────┐
│                                                                 │
│  POST   /api/entity-customer/find-similar                     │
│         → Find similar entities by name                        │
│                                                                 │
│  POST   /api/entity-customer/link-customers                   │
│         → Link multiple customers to entity                    │
│                                                                 │
│  POST   /api/entity-customer/merge-entities                   │
│         → Merge duplicate entities                             │
│                                                                 │
│  GET    /api/entity-customer/statistics                       │
│         → Get system statistics                                │
│                                                                 │
│  GET    /api/entity-customer/search                           │
│         → Search entities with filters                         │
│                                                                 │
│  GET    /api/entity-customer/entity/{id}/customers            │
│         → Get customers for entity                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🗄️ Database Schema Diagram

```
┌─────────────────────────────────┐
│        entities (NEW)           │
├─────────────────────────────────┤
│ PK  id                    INT   │
│─────────────────────────────────│
│     entity_type        VARCHAR  │ ← Index
│     entity_name        VARCHAR  │ ← Index (Unique per type)
│     passport_photo     VARCHAR  │ (for Individuals)
│     company_logo       VARCHAR  │ (for Corporates)
│     created_at         DATETIME │
│     updated_at         DATETIME │
├─────────────────────────────────┤
│ Indexes:                        │
│ • (entity_type, entity_name)    │
│ • entity_name                   │
│ • created_at                    │
└─────────────────────────────────┘
            ▲
            │
            │ One-to-Many (FK)
            │ CASCADE UPDATE
            │ RESTRICT DELETE
            │
            │
┌─────────────────────────────────┐
│      customers (UPDATED)        │
├─────────────────────────────────┤
│ PK  id                    INT   │
│ FK  entity_id             INT   │ ← NEW Foreign Key
│─────────────────────────────────│
│     customer_type      VARCHAR  │
│     status             VARCHAR  │
│     customer_name      VARCHAR  │
│     email              VARCHAR  │
│     phone              VARCHAR  │
│     property_address   VARCHAR  │
│    physcal_address   VARCHAR  │
│     notes              TEXT     │
│     customer_code      VARCHAR  │
│     created_by         INT      │
│     updated_by         INT      │
│     created_at         DATETIME │
│     updated_at         DATETIME │
│     deleted_at         DATETIME │ (Soft delete)
├─────────────────────────────────┤
│ Indexes:                        │
│ • entity_id (NEW)               │
│ • (customer_type, status)       │
│ • email                         │
│ • created_at                    │
└─────────────────────────────────┘
```

---

## 💻 Data Flow Diagram

### Customer Creation with Entity Matching

```
┌─────────────────────────────────────────────────────┐
│   User Form: Create New Customer                    │
│   Fields: name, email, phone, customer_type        │
└────────────────────┬────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────┐
│   EntityCustomerController::storeCustomer()         │
│   • Validate input                                  │
│   • Start transaction                               │
└────────────────────┬────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────┐
│   EntityService::findOrCreateEntity()               │
│                                                     │
│   1. Normalize customer_name                        │
│   2. Query: SELECT * FROM entities                  │
│      WHERE entity_name = normalized                 │
│   3. If EXACT match → Use it                        │
│   4. If NO match → Check similar (60%)              │
│   5. If similar → Show to user                      │
│   6. If no similar → CREATE new entity              │
│                                                     │
│   Return: Entity object with ID                     │
└────────────────────┬────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────┐
│   EntityService::createCustomerWithEntity()         │
│                                                     │
│   1. Attach entity_id to customer data              │
│   2. INSERT INTO customers (entity_id, ...)         │
│   3. Commit transaction                             │
│   4. Return: Customer object with entity linked     │
└────────────────────┬────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────┐
│   Response to User                                  │
│   • Redirect to customers.show                      │
│   • Display: "Customer created successfully"        │
│   • Show: Customer name + Entity name               │
└─────────────────────────────────────────────────────┘
```

---

## 🔐 Security Architecture

```
┌──────────────────────────────────────────────────────┐
│            HTTP Request                              │
└────────────────────┬─────────────────────────────────┘
                     │
                     ▼
        ┌────────────────────────────┐
        │  Authentication Middleware │
        │  (auth()->check())          │
        │  ✓ Verified / ✗ Denied     │
        └────────────────┬───────────┘
                         │ ✓ Verified
                         ▼
        ┌────────────────────────────┐
        │  Input Validation          │
        │  • Required fields         │
        │  • Format validation       │
        │  • Type checking           │
        │  ✓ Valid / ✗ Rejected      │
        └────────────────┬───────────┘
                         │ ✓ Valid
                         ▼
        ┌────────────────────────────┐
        │  Authorization Checks      │
        │  (if needed)                │
        │  • User permissions        │
        │  • Department checks       │
        │  ✓ Allowed / ✗ Forbidden   │
        └────────────────┬───────────┘
                         │ ✓ Allowed
                         ▼
        ┌────────────────────────────┐
        │  Transaction Processing    │
        │  • Database transaction    │
        │  • Foreign key validation  │
        │  • Constraint enforcement  │
        │  ✓ Committed / ✗ Rollback  │
        └────────────────┬───────────┘
                         │ ✓ Committed
                         ▼
        ┌────────────────────────────┐
        │  Response & Logging        │
        │  • User-friendly message   │
        │  • Audit trail (if needed) │
        │  • Success/Error response  │
        └────────────────────────────┘
```

---

## 📈 Performance Considerations

```
┌───────────────────────────────────────────────────────┐
│  Query Optimization                                   │
├───────────────────────────────────────────────────────┤
│                                                       │
│  Indexed Columns:                                     │
│  • entities.entity_name                               │
│  • entities.entity_type                               │
│  • entities(entity_type, entity_name)                 │
│  • customers.entity_id                                │
│                                                       │
│  Eager Loading:                                       │
│  • Entity::with('customers')                          │
│  • Customer::with('entity')                           │
│                                                       │
│  Pagination:                                          │
│  • 15 entities per page                               │
│  • 20 customers per page                              │
│                                                       │
│  Caching (Future Enhancement):                        │
│  • Cache entity count by type                         │
│  • Cache recent entities                              │
│                                                       │
└───────────────────────────────────────────────────────┘
```

---

## 🚀 Deployment Pipeline

```
┌──────────────────────────────────────────────────────┐
│  1. DEVELOPMENT                                      │
│     • Create migrations                              │
│     • Create models & relationships                  │
│     • Create service & controller                    │
│     • Write documentation                            │
│     • Local testing                                  │
└────────────────┬─────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────┐
│  2. STAGING                                          │
│     • Run migrations                                 │
│     • Add routes                                     │
│     • Clear caches                                   │
│     • Test all endpoints                             │
│     • Test with real data sample                     │
│     • UAT by QA team                                 │
└────────────────┬─────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────┐
│  3. PRODUCTION                                       │
│     • Database backup                                │
│     • Run migrations                                 │
│     • Add routes                                     │
│     • Clear caches                                   │
│     • Migrate existing data                          │
│     • Monitor for errors                             │
│     • Rollback plan ready                            │
└──────────────────────────────────────────────────────┘
```

---

## ✨ Key Features Summary

```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│  ✓ 1-to-Many Relationship      ├─ Eliminates duplication
│  ✓ Entity Matching Algorithm   ├─ Prevents duplicate IDs
│  ✓ Similarity Detection        ├─ Suggests existing entities
│  ✓ Media Management            ├─ Passport/logo storage
│  ✓ Bulk Operations             ├─ Link/merge entities
│  ✓ Transaction Support         ├─ Data integrity
│  ✓ Soft Deletes                ├─ Audit trail
│  ✓ Foreign Keys                ├─ Referential integrity
│  ✓ Advanced Search             ├─ Filters & pagination
│  ✓ Statistics & Reporting      ├─ System metrics
│  ✓ Comprehensive Logging       ├─ Error tracking
│  ✓ RESTful API                 ├─ 30+ endpoints
│  ✓ AJAX Support                ├─ Real-time operations
│  ✓ Input Validation            ├─ Server-side safety
│  ✓ Authentication              ├─ Access control
│  ✓ Error Handling              ├─ User-friendly messages
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

**Generated**: 2025-11-11  
**Status**: ✅ Complete Implementation  
**Architecture**: Scalable & Maintainable
**By IorkuaD** 
