# MediCare Clinic — Vue 3 SPA

A complete frontend for a Clinic Appointment System, built with **Vue 3 (Composition API)**, **Vue Router**, **Pinia**, **Axios**, and **Tailwind CSS**. Supports three roles: **Patient**, **Doctor**, **Admin**.

## Features
- JWT authentication (token stored in `localStorage`)
- Role-based routing with navigation guards
- Patient: dashboard, book appointment, my appointments (with cancel)
- Doctor: schedule view with date filter
- Admin: dashboard, doctor CRUD, appointment management
- Reusable components: `Navbar`, `Modal`, `StatusBadge`, `LoadingSpinner`, `AlertMessage`, `AppointmentCard`
- Toast notifications, loading spinners, inline form validation, empty states
- Fully responsive (mobile + desktop)

## Setup

```bash
cd clinic-frontend
npm install
npm run dev      # or: npm run serve
```

App runs at http://localhost:5173

### Environment
Edit `.env`:
```
VITE_API_URL=http://localhost:8000/api
```

## Backend API expected

Base URL: `VITE_API_URL`

| Method | Endpoint                          | Description                       |
|-------:|-----------------------------------|-----------------------------------|
|  POST  | `/auth/login`                     | Login → `{ token, user }`         |
|  POST  | `/auth/register`                  | Register a patient                |
|  GET   | `/doctors`                        | List doctors                      |
|  POST  | `/doctors`                        | Create (admin)                    |
|  PUT   | `/doctors/{id}`                   | Update (admin)                    |
|  DELETE| `/doctors/{id}`                   | Delete (admin)                    |
|  GET   | `/users`                          | List users (admin, optional)      |
|  GET   | `/appointments`                   | All appointments (admin)          |
|  GET   | `/appointments/patient/{id}`      | Patient's appointments            |
|  GET   | `/appointments/doctor/{id}`       | Doctor's appointments             |
|  POST  | `/appointments`                   | Book                              |
|  PUT   | `/appointments/{id}`              | Update status (admin)             |
|  DELETE| `/appointments/{id}`              | Cancel/Delete                     |

The Axios instance attaches `Authorization: Bearer <token>` automatically and redirects to `/login` on `401`.

JWT payload should contain `id`, `email`, `role` (one of `patient`, `doctor`, `admin`), and ideally `name`.

## Project Structure

```
src/
├── api/axios.js
├── assets/main.css
├── components/
│   ├── Navbar.vue
│   ├── Modal.vue
│   ├── StatusBadge.vue
│   ├── LoadingSpinner.vue
│   ├── AlertMessage.vue
│   └── AppointmentCard.vue
├── router/index.js
├── stores/
│   ├── authStore.js
│   └── alertStore.js
├── views/
│   ├── LoginView.vue
│   ├── RegisterView.vue
│   ├── patient/{Dashboard,BookAppointment,MyAppointments}View.vue
│   ├── doctor/ScheduleView.vue
│   └── admin/{AdminDashboard,DoctorManagement,AppointmentManagement}View.vue
├── App.vue
└── main.js
```

## Build for production

```bash
npm run build
npm run preview
```
