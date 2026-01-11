# nexERP API Documentation

Base URL: `http://localhost:8000/api`

## Authentication

Currently, the API is open for development. In production, implement Laravel Sanctum for API token authentication.

## Endpoints

### Products

#### List All Active Products
```http
GET /api/products
```

**Response:**
```json
[
  {
    "id": 1,
    "code": "PROD001",
    "name": "Laptop HP 15",
    "description": "Laptop HP 15 with Intel Core i5, 8GB RAM, 256GB SSD",
    "price": "699.99",
    "stock": 10,
    "active": true,
    "created_at": "2026-01-11T22:09:50.000000Z",
    "updated_at": "2026-01-11T22:09:50.000000Z"
  }
]
```

#### Create Product
```http
POST /api/products
Content-Type: application/json
```

**Request Body:**
```json
{
  "code": "PROD006",
  "name": "Webcam HD",
  "description": "1080p webcam with microphone",
  "price": 59.99,
  "stock": 20
}
```

**Response:**
```json
{
  "id": 6,
  "code": "PROD006",
  "name": "Webcam HD",
  "description": "1080p webcam with microphone",
  "price": "59.99",
  "stock": 20,
  "active": true,
  "created_at": "2026-01-11T22:30:00.000000Z",
  "updated_at": "2026-01-11T22:30:00.000000Z"
}
```

**Validation Rules:**
- `code`: required, string, unique
- `name`: required, string
- `description`: optional, string
- `price`: required, numeric, >= 0
- `stock`: required, integer, >= 0

#### Get Single Product
```http
GET /api/products/{id}
```

**Response:**
```json
{
  "id": 1,
  "code": "PROD001",
  "name": "Laptop HP 15",
  "description": "Laptop HP 15 with Intel Core i5, 8GB RAM, 256GB SSD",
  "price": "699.99",
  "stock": 10,
  "active": true,
  "created_at": "2026-01-11T22:09:50.000000Z",
  "updated_at": "2026-01-11T22:09:50.000000Z"
}
```

#### Update Product
```http
PUT /api/products/{id}
Content-Type: application/json
```

**Request Body (all fields optional):**
```json
{
  "name": "Laptop HP 15 Updated",
  "price": 649.99,
  "stock": 5,
  "active": false
}
```

#### Delete Product
```http
DELETE /api/products/{id}
```

**Response:** 204 No Content

---

### Customers

#### List All Active Customers
```http
GET /api/customers
```

**Response:**
```json
[
  {
    "id": 1,
    "code": "CUST001",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "555-1234",
    "address": null,
    "active": true,
    "created_at": "2026-01-11T22:12:00.000000Z",
    "updated_at": "2026-01-11T22:12:00.000000Z"
  }
]
```

#### Create Customer
```http
POST /api/customers
Content-Type: application/json
```

**Request Body:**
```json
{
  "code": "CUST002",
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "555-5678",
  "address": "123 Main St, City"
}
```

**Validation Rules:**
- `code`: required, string, unique
- `name`: required, string
- `email`: optional, valid email
- `phone`: optional, string
- `address`: optional, string

#### Get Single Customer
```http
GET /api/customers/{id}
```

#### Update Customer
```http
PUT /api/customers/{id}
Content-Type: application/json
```

#### Delete Customer
```http
DELETE /api/customers/{id}
```

---

### Sales

#### List All Sales
```http
GET /api/sales
```

**Response:**
```json
[
  {
    "id": 1,
    "customer_id": 1,
    "product_id": 2,
    "quantity": 3,
    "unit_price": "49.99",
    "total": "149.97",
    "created_at": "2026-01-11T22:11:49.000000Z",
    "updated_at": "2026-01-11T22:11:49.000000Z",
    "customer": {
      "id": 1,
      "code": "CUST001",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "555-1234",
      "address": null,
      "active": true,
      "created_at": "2026-01-11T22:12:00.000000Z",
      "updated_at": "2026-01-11T22:12:00.000000Z"
    },
    "product": {
      "id": 2,
      "code": "PROD002",
      "name": "Mouse Logitech MX",
      "description": "Wireless mouse with ergonomic design",
      "price": "49.99",
      "stock": 47,
      "active": true,
      "created_at": "2026-01-11T22:09:50.000000Z",
      "updated_at": "2026-01-11T22:11:49.000000Z"
    }
  }
]
```

#### Create Sale
```http
POST /api/sales
Content-Type: application/json
```

**Request Body:**
```json
{
  "product_id": 2,
  "quantity": 3,
  "customer_id": 1
}
```

**Note:** 
- `customer_id` is optional
- `unit_price` and `total` are calculated automatically
- Stock is automatically decremented
- Returns error if insufficient stock

**Response:**
```json
{
  "id": 3,
  "customer_id": 1,
  "product_id": 2,
  "quantity": 3,
  "unit_price": "49.99",
  "total": "149.97",
  "created_at": "2026-01-11T22:30:00.000000Z",
  "updated_at": "2026-01-11T22:30:00.000000Z",
  "customer": { ... },
  "product": { ... }
}
```

**Validation Rules:**
- `product_id`: required, must exist
- `customer_id`: optional, must exist if provided
- `quantity`: required, integer, >= 1

**Error Response (Insufficient Stock):**
```json
{
  "error": "Insufficient stock"
}
```
Status Code: 400

#### Get Single Sale
```http
GET /api/sales/{id}
```

#### Update Sale
```http
PUT /api/sales/{id}
```

**Response:**
```json
{
  "error": "Sales cannot be updated"
}
```
Status Code: 403

Note: Sales are immutable for data integrity.

#### Delete Sale
```http
DELETE /api/sales/{id}
```

**Response:** 204 No Content

Note: Deleting a sale does NOT restore stock. This is intentional for audit purposes.

---

## Error Responses

### Validation Error
**Status Code:** 422

```json
{
  "message": "The code field is required. (and 1 more error)",
  "errors": {
    "code": [
      "The code field is required."
    ],
    "price": [
      "The price field is required."
    ]
  }
}
```

### Not Found
**Status Code:** 404

```json
{
  "message": "No query results for model [App\\Models\\Product] 999"
}
```

### Server Error
**Status Code:** 500

```json
{
  "message": "Server Error"
}
```

---

## Data Models

### Product Schema
```
- id: integer (auto-increment)
- code: string (unique, indexed)
- name: string
- description: text (nullable)
- price: decimal(10,2)
- stock: integer (default: 0)
- active: boolean (default: true)
- created_at: timestamp
- updated_at: timestamp
```

### Customer Schema
```
- id: integer (auto-increment)
- code: string (unique, indexed)
- name: string
- email: string (nullable)
- phone: string (nullable)
- address: text (nullable)
- active: boolean (default: true)
- created_at: timestamp
- updated_at: timestamp
```

### Sale Schema
```
- id: integer (auto-increment)
- customer_id: integer (foreign key, nullable, on delete: set null)
- product_id: integer (foreign key, on delete: cascade)
- quantity: integer
- unit_price: decimal(10,2)
- total: decimal(10,2)
- created_at: timestamp
- updated_at: timestamp
```

---

## Rate Limiting

Currently no rate limiting is applied. For production, configure in `app/Http/Kernel.php`:

```php
'api' => [
    'throttle:60,1', // 60 requests per minute
],
```

---

## CORS Configuration

CORS is enabled by default in Laravel. Configure in `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
```

For production, restrict `allowed_origins` to your TUI client domains.

---

## Testing with curl

### Create a product
```bash
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "code": "TEST001",
    "name": "Test Product",
    "price": 19.99,
    "stock": 100
  }'
```

### Process a sale
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "quantity": 2
  }'
```

### List all products
```bash
curl http://localhost:8000/api/products | jq '.'
```

---

## Future Enhancements

- [ ] API Authentication (Laravel Sanctum)
- [ ] Pagination for list endpoints
- [ ] Advanced filtering and search
- [ ] Bulk operations
- [ ] Export endpoints (CSV, PDF)
- [ ] Statistics and analytics endpoints
- [ ] Webhook support for real-time updates
