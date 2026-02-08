# 🌟 The Star Oracle — Real-time Asteroid Intelligence Platform

A full-stack web application for tracking near-Earth asteroids in real time using NASA's NeoWs API. Features live data feeds, risk analysis, 3D orbit visualization, a researcher portal, and a custom risk scoring algorithm.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![NASA API](https://img.shields.io/badge/NASA_API-0B3D91?style=for-the-badge&logo=nasa&logoColor=white)

---

## 📋 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Screenshots](#-screenshots)
- [Project Structure](#-project-structure)
- [Installation & Setup](#-installation--setup)
- [Database Schema](#-database-schema)
- [API Endpoints](#-api-endpoints)
- [Risk Score Algorithm](#-risk-score-algorithm)
- [Pages Overview](#-pages-overview)
- [Authentication](#-authentication)
- [Configuration](#-configuration)
- [License](#-license)

---

## ✨ Features

- **Live Asteroid Feed** — Real-time data from NASA's Near Earth Object Web Service
- **Custom Risk Scoring** — Proprietary 0–100 risk algorithm based on proximity, size, velocity, and hazard classification
- **3D Orbit Visualization** — Interactive 3D rendering of asteroid orbital paths
- **Risk Analysis Dashboard** — Gauge visualization, scatter plots, heat maps, and high-risk object tracking
- **Researcher Portal** — 9-panel dashboard with notes, data export (CSV/JSON), session management, and API key validation
- **Watchlist** — Track specific asteroids with personal notes
- **Alert System** — 5-tier risk badges (Safe → Hazardous) with upcoming close approach tracking
- **User Authentication** — JWT-based auth with role separation (User / Researcher / Admin)
- **Email Verification** — Token-based email verification on registration
- **Responsive Design** — Glass-morphism UI with animated starfield backgrounds

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript, Tailwind CSS (CDN), Chart.js |
| **Backend** | PHP (REST API) |
| **Database** | MySQL via PDO |
| **Server** | Apache (XAMPP) |
| **External API** | [NASA NeoWs](https://api.nasa.gov/) (Near Earth Object Web Service) |
| **Auth** | Custom JWT (HS256) with 24-hour expiry |
| **Fonts** | Poppins, Roboto, JetBrains Mono |

---

## 📸 Screenshots

> Add your screenshots here after deployment.

---

## 📁 Project Structure

```
thestaroracle/
├── backend/
│   ├── api/
│   │   ├── asteroids.php          # NASA NEO data + risk scoring
│   │   ├── login_user.php         # User authentication
│   │   ├── login_researcher.php   # Researcher authentication
│   │   ├── register.php           # User/researcher registration
│   │   ├── logout.php             # Session invalidation
│   │   ├── verify_email.php       # Email verification
│   │   ├── watchlist.php          # Watchlist CRUD
│   │   ├── settings.php           # User preferences
│   │   ├── researcher.php         # Researcher-specific endpoints
│   │   └── test.php               # API health check
│   └── config/
│       ├── db.php                 # Database connection (PDO)
│       └── auth.php               # JWT auth helpers
├── database/
│   └── schema.sql                 # Full database schema
├── frontend/
│   ├── index.html                 # Landing page
│   ├── dashboard.html             # User dashboard
│   ├── live-feed.html             # Real-time asteroid feed
│   ├── asteroid-detail.html       # Single asteroid details
│   ├── risk-analysis.html         # Risk analysis dashboard
│   ├── orbit-3d.html              # 3D orbit visualization
│   ├── alerts.html                # Alert notifications
│   ├── register.html              # Registration page
│   ├── login-user.html            # User login
│   ├── login-researcher.html      # Researcher login
│   ├── researcher-panel.html      # Researcher 9-panel dashboard
│   ├── css/
│   │   └── main.css               # Global styles
│   └── js/
│       └── main.js                # Shared JS (API, auth helpers)
└── README.md
```

---

## 🚀 Installation & Setup

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP)
- A web browser
- (Optional) A [NASA API Key](https://api.nasa.gov/) — the app includes a default key

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/thestaroracle.git
   ```

2. **Move to XAMPP htdocs**
   ```bash
   cp -r thestaroracle /path/to/xampp/htdocs/
   ```
   Or on Windows, copy the folder to `C:\xampp\htdocs\`

3. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL**

4. **Create the database**
   - Open [phpMyAdmin](http://localhost/phpmyadmin)
   - Create a new database named `star_oracle`
   - Import `database/schema.sql`:
     - Click the `star_oracle` database
     - Go to **Import** tab
     - Choose `database/schema.sql`
     - Click **Go**

5. **Configure the database** (optional)
   
   Default config in `backend/config/db.php`:
   ```php
   $host = 'localhost';
   $dbname = 'star_oracle';
   $user = 'root';
   $pass = '';
   ```
   Update these values if your MySQL credentials differ.

6. **Open the application**
   ```
   http://localhost/thestaroracle/frontend/index.html
   ```

---

## 🗄 Database Schema

The application uses **7 tables** in the `star_oracle` database:

| Table | Purpose |
|---|---|
| `users` | User accounts with role-based access (user / researcher / admin) |
| `researchers` | Extended researcher profiles linked to users |
| `sessions` | JWT session tracking with IP and user-agent |
| `watchlist` | User asteroid tracking list with notes |
| `alerts` | Notification system (close approach, hazardous, velocity, custom) |
| `user_preferences` | Notification settings and display preferences |
| `research_notes` | Researcher notes on specific asteroids with optional risk overrides |

### Entity Relationship

```
users (1) ──── (N) sessions
users (1) ──── (1) researchers
users (1) ──── (N) watchlist
users (1) ──── (N) alerts
users (1) ──── (1) user_preferences
researchers (1) ── (N) research_notes
```

---

## 📡 API Endpoints

### Public

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/register.php` | Register a new user or researcher |
| `POST` | `/api/login_user.php` | User login → returns JWT |
| `POST` | `/api/login_researcher.php` | Researcher login (email + password + research ID) → returns JWT |
| `GET` | `/api/verify_email.php?token=...` | Verify email address |
| `GET` | `/api/asteroids.php` | Fetch asteroid data from NASA (params: `start_date`, `end_date`) |

### Authenticated (requires `Authorization: Bearer <token>`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/watchlist.php?action=list` | Get user's watchlist |
| `POST` | `/api/watchlist.php?action=add` | Add asteroid to watchlist |
| `DELETE` | `/api/watchlist.php?action=remove` | Remove from watchlist |
| `GET` | `/api/settings.php` | Get user preferences |
| `POST` | `/api/settings.php` | Update preferences |
| `POST` | `/api/logout.php` | Invalidate session |

### Researcher Only (requires researcher role)

| Method | Action | Description |
|---|---|---|
| `GET` | `?action=profile` | Researcher profile + stats |
| `GET` | `?action=notes` | List research notes |
| `POST` | `?action=notes` | Create/update note |
| `DELETE` | `?action=notes` | Delete note |
| `GET` | `?action=sessions` | Active sessions list |
| `GET` | `?action=watchlist` | Researcher watchlist |
| `POST` | `?action=watchlist` | Add to watchlist |
| `DELETE` | `?action=watchlist` | Remove from watchlist |
| `GET` | `?action=export` | Export data (CSV/JSON) |
| `GET` | `?action=alerts` | Researcher alerts |
| `GET` | `?action=stats` | Dashboard statistics |
| `POST` | `?action=apikey` | Validate NASA API key |

---

## 🎯 Risk Score Algorithm

Each asteroid receives a **custom risk score (0–100)** calculated from:

| Factor | Points | Condition |
|---|---|---|
| **Hazardous flag** | +40 | Classified as potentially hazardous by NASA |
| **Proximity** | +30 | Miss distance < 1 million km |
| | +20 | Miss distance < 5 million km |
| | +10 | Miss distance < 10 million km |
| **Size** | +20 | Average diameter > 1 km |
| | +15 | Average diameter > 500 m |
| | +10 | Average diameter > 100 m |
| | +5 | Average diameter > 50 m |
| **Velocity** | +10 | Relative velocity > 100,000 km/h |
| | +5 | Relative velocity > 50,000 km/h |

### Risk Tiers

| Score | Label | Color |
|---|---|---|
| 0–20 | SAFE | 🟢 Green |
| 21–40 | LOW RISK | 🟡 Yellow |
| 41–60 | MODERATE | 🟠 Orange |
| 61–80 | HIGH RISK | 🔴 Red |
| 81–100 | HAZARDOUS | 🔴 Deep Red |

---

## 📄 Pages Overview

| Page | Description |
|---|---|
| **Landing Page** | Animated hero section with live stats, feature showcase, and how-it-works section |
| **Dashboard** | Overview of tracked asteroids, watchlist, and quick navigation |
| **Live Feed** | Real-time asteroid data with search, sort, filter, and date range selection (max 7 days) |
| **Asteroid Detail** | Full breakdown — orbital data, physical characteristics, close approach info, risk score ring, orbit preview |
| **Risk Analysis** | Semi-circular gauge, bubble chart, heat map, and high-risk object list |
| **3D Orbit** | Interactive 3D visualization of asteroid orbital paths |
| **Alerts** | Upcoming close approaches with 5-tier risk badges, search, pagination |
| **Researcher Panel** | 9-panel dashboard: profile, notes editor, sessions, watchlist, data export, alerts, stats, API console, activity log |
| **Register** | Role-based registration (User or Researcher) with password strength meter |
| **User Login** | Email + password authentication |
| **Researcher Login** | Email + password + Research ID authentication |

---

## 🔐 Authentication

- **Mechanism:** Custom JWT (JSON Web Token)
- **Algorithm:** HMAC-SHA256
- **Token Expiry:** 24 hours
- **Transport:** `Authorization: Bearer <token>` header
- **Storage:** `localStorage` (token + user object)
- **Session Tracking:** Tokens stored server-side in `sessions` table with IP address and user-agent
- **Roles:** `user`, `researcher`, `admin`
- **Back-button Prevention:** `history.pushState` + `onpopstate` guards on all protected pages
- **Logout:** Fire-and-forget server call + `localStorage.clear()` + `sessionStorage.clear()` + `window.location.replace()`

---

## ⚙ Configuration

### Database (`backend/config/db.php`)
```php
$host = 'localhost';
$dbname = 'star_oracle';
$user = 'root';
$pass = '';
```

### NASA API Key
The app uses a built-in NASA API key. To use your own:
1. Get a free key at [https://api.nasa.gov/](https://api.nasa.gov/)
2. Replace the `NASA_API_KEY` constant in the frontend files or use the Researcher Panel's API key validator

### JWT Secret
Located in `backend/config/auth.php`. Change the secret key for production:
```php
define('JWT_SECRET', 'your-secure-secret-key');
```

---

## 🌐 External APIs

| API | Provider | Usage |
|---|---|---|
| [NeoWs](https://api.nasa.gov/neo/) | NASA | Near-Earth Object data — asteroid names, diameters, velocities, miss distances, orbital elements, hazard classification |

---

## 📝 License

This project is open source and available under the [MIT License](LICENSE).

---

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

<p align="center">
  Built with ☄️ by CodeNextLab
</p>
