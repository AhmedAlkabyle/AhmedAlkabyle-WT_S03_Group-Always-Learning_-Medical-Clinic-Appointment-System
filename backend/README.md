# MediCare Clinic — PHP Slim 4 Backend

REST API backend for the MediCare Clinic Appointment System.  
Built with **PHP 8.0+**, **Slim 4**, **Firebase JWT**, and **MySQL**.

---

## Requirements

| Tool       | Version   |
|------------|-----------|
| PHP        | 8.0+      |
| MySQL      | 5.7+ / 8.0 |
| Composer   | 2.x       |
| XAMPP      | Any recent |

---

## Setup

```bash
# 1. Install PHP dependencies
cd backend
composer install

# 2. Import the database
#    Open phpMyAdmin → Import → select database/schema.sql
#    OR run:
mysql -u root -p < database/schema.sql

# 3. Configure environment
#    Edit .env and set your DB credentials if needed
#    (defaults work for a standard XAMPP install with no root password)

# 4. Start the development server
php -S localhost:8000 -t public
```

The API will be available at **http://localhost:8000**

---

## Demo Credentials

| Role    | Email                  | Password   |
|---------|------------------------|------------|
| Patient | patient@clinic.test    | password   |
| Doctor  | doctor@clinic.test     | password   |
| Doctor  | doctor2@clinic.test    | password   |
| Admin   | admin@clinic.test      | password   |

---

## API Endpoints

### Auth
| Method | Path              | Auth     | Description           |
|--------|-------------------|----------|-----------------------|
| POST   | /auth/register    | Public   | Register new user     |
| POST   | /auth/login       | Public   | Login, returns JWT    |

### Profile (self)
| Method | Path              | Auth     | Description                  |
|--------|-------------------|----------|------------------------------|
| GET    | /me               | Any      | Get own profile              |
| PUT    | /me               | Any      | Update own profile           |
| PUT    | /me/password      | Any      | Change own password          |

### Users (admin only)
| Method | Path                  | Auth   | Description           |
|--------|-----------------------|--------|-----------------------|
| GET    | /users                | Admin  | List all users        |
| GET    | /users/{id}           | Admin  | Get single user       |
| PUT    | /users/{id}           | Admin  | Update user           |
| PUT    | /users/{id}/status    | Admin  | Toggle is_active      |
| DELETE | /users/{id}           | Admin  | Delete user           |

### Doctors
| Method | Path              | Auth     | Description              |
|--------|-------------------|----------|--------------------------|
| GET    | /doctors          | Public   | List all doctors         |
| GET    | /doctors/{id}     | Public   | Get single doctor        |
| POST   | /doctors          | Admin    | Create doctor profile    |
| PUT    | /doctors/{id}     | Admin    | Update doctor profile    |
| DELETE | /doctors/{id}     | Admin    | Delete doctor profile    |

### Appointments
| Method | Path                              | Auth           | Description                 |
|--------|-----------------------------------|----------------|-----------------------------|
| GET    | /appointments                     | Admin          | List all appointments       |
| GET    | /appointments/{id}                | Any authed     | Get single appointment      |
| GET    | /appointments/patient/{id}        | Patient/Admin  | Patient's appointments      |
| GET    | /appointments/doctor/{id}         | Doctor/Admin   | Doctor's appointments       |
| POST   | /appointments                     | Patient        | Book appointment            |
| PUT    | /appointments/{id}                | Admin          | Update status               |
| PUT    | /appointments/{id}/complete       | Doctor         | Mark completed              |
| PUT    | /appointments/{id}/comment        | Doctor         | Add doctor comment          |
| DELETE | /appointments/{id}                | Any authed     | Cancel appointment          |

### Off Day Requests
| Method | Path                      | Auth           | Description              |
|--------|---------------------------|----------------|--------------------------|
| GET    | /off-days                 | Admin          | List all requests        |
| GET    | /off-days/doctor/{id}     | Doctor/Admin   | Doctor's requests        |
| POST   | /off-days                 | Doctor         | Submit request           |
| PUT    | /off-days/{id}            | Admin          | Approve / reject         |
| DELETE | /off-days/{id}            | Doctor         | Cancel pending request   |

---

## Authentication

All protected routes require a `Bearer` token in the `Authorization` header:

```
Authorization: Bearer <token>
```

Obtain a token via `POST /auth/login`.

---

## Response Codes

| Code | Meaning                     |
|------|-----------------------------|
| 200  | OK                          |
| 201  | Created                     |
| 400  | Bad Request (validation)    |
| 401  | Unauthorized (bad/no token) |
| 403  | Forbidden (wrong role)      |
| 404  | Not Found                   |
| 422  | Unprocessable (e.g. duplicate email) |
| 500  | Internal Server Error       |
