# Basic User CRUD with Symfony

This pet project is just for demonstration.

## Installation

```bash
composer install
```
Setup your database configuration (refer to [Symfony config](https://symfony.com/doc/current/doctrine.html#configuring-the-database))

```bash
 php bin/console doctrine:migrations:migrate
```

## Notes
- I didn’t user Symfony’s built in authentication mechanism since purpose of the project is showing usage of CRUD actions. Instead, I just used UserPasswordEncoderInterface to hash plain passwords to secure them safely in DB.
- Annotations are used for Doctrine, Routes, Forms and Validations.
- Login mechanism is based on Symfony sessions. It may not be enough for a real authentication system.
- Bootstrap 4 is used in general layout
- No unit tests yet
- Navigation menu items could be shown/hidden by user status. I didn’t spend time on that.
- Flash sessions are used for most error/success messages
- UserRepository has 2 custom methods to make Doctrine actions are more developer friendly
- User login and registration forms are bind to the User entity while user edit is a custom form since it has new password fields
