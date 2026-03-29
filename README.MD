# Local Development Setup

This guide will walk you ghtough setting up a local dev env for this project using Docker.

## Prerequisites

You need to have [Docker Desktop](https://docs.docker.com/desktop/setup/install/linux/) installed on your machine. This includes Docker and Docker Compose, which are essential for running the local environment.

***

### 1. Initial Setup

First, create a project directory and a `docker-compose.yml` file to define your services.

1. Create a new directory for your project:

```bash
mkdir crm-sfc
cd crm-sfc
```

2. Create a `docker-compose.yml` file and add the following content. This configuration sets up a MariaDB database and the EspoCRM application server, linking them together on a virtual network.

```yaml
services:
  espocrm-db:
    container_name: espocrm-db
    image: mariadb:10.6
    restart: always
    ports:
      - "3306:3306"
    environment:
      - MARIADB_ROOT_PASSWORD=your_root_password
      - MARIADB_DATABASE=espocrm_db
      - MARIADB_USER=espocrm_user
      - MARIADB_PASSWORD=your_db_password
    volumes:
      - espocrm-db:/var/lib/mysql

  espocrm:
    container_name: espocrm
    image: espocrm/espocrm:latest
    restart: always
    depends_on:
      - espocrm-db
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html
    environment:
      - ESPOCRM_DATABASE_HOST=espocrm-db
      - ESPOCRM_DATABASE_USER=espocrm_user
      - ESPOCRM_DATABASE_PASSWORD=your_db_password
      - ESPOCRM_DATABASE_NAME=espocrm_db
      - ESPOCRM_SITE_URL=http://localhost:8080
      - ESPOCRM_ADMIN_USERNAME=admin
      - ESPOCRM_ADMIN_PASSWORD=admin

volumes:
  espocrm-db:
```

3. Start the Docker containers:

```bash
docker compose up -d
```

4. Fix permissions

Change ownership of the newly create `app` folder.
```bash
sudo chown -R www-data:www-data app/;
sudo chmod -R g+w app/;
sudo chmod -R g+s app/;
```

If your user is not part of the www-data user group yet, let us fix that right now.
```bash
sudo usermod -a -G www-data $user

#Either log out and back in or do a temporary group switch for the current shell
newgrp www-data
```

***

### 2. Get the Application & Database

Your `crm-sfc` directory now contains a Docker setup, but you would start a fresh install when you would hoist your containers. You need to pull the application files from the Git repository and restore the production database.

1. Pull the application code.

    The `docker-compose.yml` file mounts a local `app` directory to the container. You need to clone the Git repository into this folder.

```bash
cd app
git init
git remote add origin [this_gitlab_repo_url]
git pull origin master
cd ..
```

2. Backup the production DB (sanitized dump).

    Create a sanitized database dump file. The sanitizer SQL is appended to the dump so it runs on import.

```bash
CONFIRM=1 DB_HOST=your_prod_host DB_USER=your_prod_user DB_PASSWORD=your_prod_password DB_NAME=your_prod_db OUT_FILE=db.sanitized.sql app/bin/db-copy-sanitize-dump.sh
```

3. Import the database locally.

    Copy the `db.sql` file to your local `crm-sfc` directory. Then, run the following command to import the database into your local Docker container. The `mysql` client will connect to your Docker container's database service.

```bash
mysql -u espocrm_user -p --host=127.0.0.1 --port=3306 espocrm_db < db.sanitized.sql
```

Use `your_db_password` for the password.

4. Sanitize the copied database (required).

Run the sanitizer script to prevent live syncs, cronjobs, and outbound mail in local/test.

```bash
CONFIRM=1 DB_PASSWORD=your_db_password app/bin/db-copy-sanitize.sh
```

If your DB is not local, you must also set `CONFIRM_REMOTE=1`. You can override connection settings with `DB_HOST`, `DB_PORT`, `DB_NAME`, and `DB_USER`.

BELANGRIJK BIJ KOPIE
Volgende queries moeten gerund worden op de test/lokale omgeving na het nemen van een kopie. Anders kan dit issues geven met foute syncs en mails:

```sql
-- 1. Wis alle Google Calendar (en andere externe) tokens van gebruikers
TRUNCATE TABLE external_account;

-- 2. Schakel alle Scheduled Jobs (Cronjobs) uit, zodat er op de achtergrond niets begint te draaien
UPDATE scheduled_job SET status = 'Inactive';

-- 3. Schakel alle Inkomende (Groep) mailboxen uit
UPDATE inbound_email SET status = 'Inactive';

-- 4. Schakel alle Persoonlijke mailboxen (van users) uit
UPDATE email_account SET status = 'Inactive';

-- 5. Wis de Google Integratie API keys op systeemniveau
UPDATE integration SET data = NULL WHERE id = 'Google';
```

***

### 3. Finalizing the Setup

Now that the application files and database are in place, you need to update the configuration to work in the local environment.

1. Add a config-internal file

    Add a `config-internal.php` file similar to what is in production. Replace the DB settings with the DB settings from the `docker-compose.yml` file and replace the Vanko Webhooks with dummy-urls so the calls will silently fail.
