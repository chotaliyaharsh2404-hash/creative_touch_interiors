# Creative Touch Interiors - PHP Website

A complete interior design company website built with PHP, MySQL, HTML, CSS, and JavaScript.

## Project Structure

```
Creative Touch Interiors/
├── admin/
│   ├── dashboard.php      # Admin dashboard with statistics
│   └── login.php          # Admin login page
├── css/
│   └── style.css          # Main stylesheet
├── includes/
│   └── config.php         # Database configuration and helper functions
├── js/
│   └── script.js          # JavaScript for interactivity
├── uploads/               # Directory for file uploads
├── about.php              # About page
├── consultation.php       # Consultation/enquiry form
├── contact.php            # Contact page
├── database.sql           # MySQL database schema
├── gallery.php            # Gallery page
├── index.php              # Home page
├── projects.php           # Projects/portfolio page
└── services.php           # Services page
```

## Setup Instructions

### 1. Import Database

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `creative_touch_interiors`
3. Import the `database.sql` file into the database
4. The database will create all necessary tables and insert default admin user

### 2. Configure Database Connection

Open `includes/config.php` and update the database credentials if needed:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'creative_touch_interiors');
```

### 3. Update Base URL

In `includes/config.php`, update the BASE_URL to match your server:

```php
define('BASE_URL', 'http://localhost/Creative%20Touch%20Interiors/');
```

### 4. Access the Website

- **Public Website**: http://localhost/Creative%20Touch%20Interiors/
- **Admin Panel**: http://localhost/Creative%20Touch%20Interiors/admin/login.php

### 5. Default Admin Login

- **Username**: admin
- **Password**: admin123

⚠️ **Important**: Change the default admin password after first login!

## Features

### User-Facing Pages

- **Home Page**: Hero section, services preview, featured projects, gallery, testimonials, team, CTA
- **About Page**: Company story, vision, mission, design philosophy, why choose us, team
- **Services Page**: Service categories, how we work, benefits
- **Projects Page**: Portfolio with category filtering, search functionality
- **Gallery Page**: Image gallery with category filtering, lightbox view
- **Consultation Page**: Comprehensive enquiry form for project requests
- **Contact Page**: Contact form and information

### Admin Panel

- **Dashboard**: Statistics, recent enquiries, upcoming consultations, recent projects, performance overview
- **Authentication**: Secure login with session management
- **Navigation**: Sidebar with links to all admin sections

## Database Tables

- `admin_users` - Admin user accounts
- `projects` - Project portfolio
- `services` - Service offerings
- `leads` - Enquiries/leads from consultation form
- `consultations` - Scheduled consultations
- `testimonials` - Client testimonials
- `faqs` - Frequently asked questions
- `team_members` - Team member profiles
- `gallery_images` - Gallery images
- `website_content` - Editable website content
- `notifications` - System notifications

## Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Styling**: Custom CSS with responsive design
- **Icons**: Emoji icons (can be replaced with icon fonts)

## Security Features

- SQL injection prevention using prepared statements
- XSS prevention with output sanitization
- Session-based authentication
- Password hashing with PHP's password_hash()

## Customization

### Adding Sample Data

You can add sample data by inserting records into the database tables:

```sql
INSERT INTO services (title, slug, description, category, icon, featured) VALUES 
('Full Home Design', 'full-home-design', 'Complete home interior design services', 'residential', 'home', 1);
```

### Modifying Styles

Edit `css/style.css` to customize the appearance. The CSS uses a modular structure with:
- Reset and base styles
- Navigation
- Hero sections
- Cards and grids
- Forms
- Tables
- Admin panel styles
- Responsive breakpoints

### Adding New Pages

1. Create a new PHP file in the root directory
2. Include the config file: `require_once 'includes/config.php';`
3. Use the existing pages as templates for structure
4. Add navigation link to the navbar

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Support

For issues or questions, please refer to the code comments or contact the development team.

## License

This project is proprietary software for Creative Touch Interiors.
