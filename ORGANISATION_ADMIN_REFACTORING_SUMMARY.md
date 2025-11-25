# Organisation Admin Refactoring Summary

## Overview
This refactoring removes the `admin_user_id` field from organisations and replaces it with `admin_name` and `admin_email` fields. The system no longer requires users to exist in the system before being set as organisation administrators.

## Key Changes

### 1. Database Schema Changes
**File:** `wp-content/themes/ipm/functions.php`

**Changed:**
- Removed: `admin_user_id bigint(20) NULL`
- Added: `admin_name varchar(255) NULL`
- Added: `admin_email varchar(255) NULL`
- Removed index: `KEY admin_user_id (admin_user_id)`
- Added index: `KEY admin_email (admin_email)`

**How to check admin exists:**
```php
// OLD WAY (admin_user_id):
$has_admin = !empty($org->admin_user_id);

// NEW WAY (admin_name and admin_email):
$has_admin = !empty($org->admin_name) && !empty($org->admin_email);
```

---

### 2. Admin Check Function Updated
**File:** `wp-content/themes/ipm/functions.php`

**Function:** `iipm_is_organisation_admin()`

**Changed:**
- Now checks if user's email matches `admin_email` in organisations table
- Verifies both `admin_name` and `admin_email` are not null
- No longer checks `admin_user_id`

```php
// NEW implementation checks:
// 1. User has 'iipm_corporate_admin' role, OR
// 2. User's email matches admin_email in any organisation (where admin_name and admin_email are not null)
```

---

### 3. Simplified Admin Setup Function
**File:** `wp-content/themes/ipm/includes/organisation-management-handlers.php`

**Function:** `iipm_setup_organisation_admin()`

**Changed:**
- Now simply stores `admin_name` and `admin_email` directly in the organisation table
- No longer checks if user exists in the system
- Requires both admin name and admin email to be provided
- Sends invitation email (optional)
- Does NOT create user accounts

**New Flow:**
1. Admin provides name and email
2. System stores name and email in organisation table
3. System sends invitation email (if requested)
4. That's it! No user creation at this stage

---

### 4. Admin Registration Process Updated
**File:** `wp-content/themes/ipm/includes/organisation-management-handlers.php`

**Function:** `iipm_process_organisation_admin_registration()`

**Changed:**
- Verifies that registration email matches the `admin_email` in organisation
- Creates user account when admin registers
- Sets user role to `iipm_corporate_admin`
- No longer updates `admin_user_id` (it doesn't exist anymore)

---

### 5. Direct Assign Feature Removed
**Files Modified:**
- `wp-content/themes/ipm/template-organisation-management.php` - Removed UI button and modal
- `wp-content/themes/ipm/includes/direct-admin-assignment.php` - **File deleted**
- `wp-content/themes/ipm/functions.php` - Removed handler functions

**What was removed:**
- "Direct Assign Admin" button in UI
- Direct assignment modal
- All JavaScript for direct assignment
- AJAX handler: `iipm_handle_direct_admin_assignment()`
- AJAX handler: `iipm_get_all_users_for_assignment()`

---

### 6. SQL Queries Updated

**All files with SQL queries updated:**
1. `wp-content/themes/ipm/functions.php`
2. `wp-content/themes/ipm/template-organisation-management.php`
3. `wp-content/themes/ipm/includes/organisation-management-handlers.php`
4. `wp-content/themes/ipm/includes/payment-management.php`
5. `wp-content/themes/ipm/includes/member-management-handlers.php`
6. `wp-content/themes/ipm/includes/enhanced-ajax-handlers.php`

**Changes made:**
- Removed JOINs with `wp_users` table when fetching admin info
- Changed admin checks from `admin_user_id = %d` to `admin_email = %s AND admin_name IS NOT NULL AND admin_email IS NOT NULL`
- Updated SELECT queries to fetch `admin_name, admin_email` instead of `admin_user_id`
- Updated WHERE clauses for organisation admin filtering

---

### 7. UI Changes
**File:** `wp-content/themes/ipm/template-organisation-management.php`

**Changes:**
- Stats now count organisations with admin by checking: `admin_name IS NOT NULL AND admin_email IS NOT NULL`
- Organisation list query no longer JOINs with users table
- Admin status determined by checking if both `admin_name` and `admin_email` exist
- Removed "Direct Assign Admin" button
- Removed Direct Assignment modal (HTML + JavaScript)

---

## Database Migration

### Migration Script Created
**File:** `wp-content/themes/ipm/includes/migrate-admin-fields.php`

This script provides:
1. Automated migration function
2. WordPress admin page to run migration
3. Manual SQL commands if preferred

### Migration Steps

#### Option 1: Use the Admin Page
1. Go to WordPress Admin → Tools → IIPM Migration
2. Click "Run Migration" button
3. Review the results

#### Option 2: Run SQL Manually
```sql
-- Add new columns
ALTER TABLE `wp_test_iipm_organisations` 
    ADD COLUMN `admin_name` varchar(255) NULL AFTER `country`,
    ADD COLUMN `admin_email` varchar(255) NULL AFTER `admin_name`;

-- Migrate existing data (do this in PHP or manually for each org)
UPDATE `wp_test_iipm_organisations` o
JOIN `wp_users` u ON o.admin_user_id = u.ID
SET o.admin_name = u.display_name,
    o.admin_email = u.user_email
WHERE o.admin_user_id IS NOT NULL;

-- Add index for admin_email
ALTER TABLE `wp_test_iipm_organisations` 
    ADD INDEX `admin_email` (`admin_email`);

-- After verifying migration, optionally remove old column:
ALTER TABLE `wp_test_iipm_organisations` 
    DROP COLUMN `admin_user_id`,
    DROP INDEX `admin_user_id`;
```

---

## Testing Checklist

### 1. Admin Setup
- [ ] Can create organisation without admin
- [ ] Can add admin to organisation (name + email)
- [ ] Admin invitation email is sent correctly
- [ ] Organisation shows "Pending Setup" status correctly
- [ ] Organisation shows "Setup Complete" status after admin is added

### 2. Admin Registration
- [ ] Admin can register using invitation link
- [ ] Admin email must match organisation's admin_email
- [ ] Admin account is created successfully
- [ ] Admin gets 'iipm_corporate_admin' role
- [ ] Admin can log in after registration

### 3. Admin Access
- [ ] Admin can access their organisation's dashboard
- [ ] Admin can view organisation members
- [ ] Admin can manage invitations
- [ ] Admin can import members
- [ ] Admin cannot access other organisations' data

### 4. Organisation List
- [ ] Stats show correct count of organisations with admins
- [ ] Organisation table displays correctly
- [ ] Admin status badge shows correctly
- [ ] Search and filter work correctly
- [ ] Export functionality works

### 5. Migration
- [ ] Migration script runs without errors
- [ ] All existing admin_user_id data is migrated to admin_name/admin_email
- [ ] No data is lost
- [ ] Existing admins can still access their organisations

---

## Breaking Changes

### For Developers
If you have custom code that references `admin_user_id`, you must update it:

**Before:**
```php
// Checking if admin exists
if ($org->admin_user_id) { ... }

// Getting admin user
$admin_user = get_user_by('id', $org->admin_user_id);

// Querying organisations
SELECT * FROM organisations WHERE admin_user_id = %d
```

**After:**
```php
// Checking if admin exists
if (!empty($org->admin_name) && !empty($org->admin_email)) { ... }

// Getting admin user (if they've registered)
$admin_user = get_user_by('email', $org->admin_email);

// Querying organisations
SELECT * FROM organisations 
WHERE admin_email = %s 
AND admin_name IS NOT NULL 
AND admin_email IS NOT NULL
```

### For Users
- The "Direct Assign Admin" feature has been removed
- Admins must be set up using email invitation only
- Admin information is stored even if they haven't registered yet
- Organisation can have admin details (name + email) without the admin having a user account

---

## Benefits of This Refactoring

1. **Simplified Admin Management**: No need for users to exist before being set as admins
2. **Better Data Integrity**: Admin information stored directly, not dependent on user accounts
3. **Clearer Intent**: Admin name and email are explicit, not inferred from user relationships
4. **Easier Testing**: Can set up organisations with admin details without creating user accounts
5. **More Flexible**: Admin information preserved even if user account is deleted
6. **Better Performance**: Fewer JOINs needed when querying organisations

---

## Files Modified

### Created:
- `wp-content/themes/ipm/includes/migrate-admin-fields.php`

### Deleted:
- `wp-content/themes/ipm/includes/direct-admin-assignment.php`

### Modified:
- `wp-content/themes/ipm/functions.php`
- `wp-content/themes/ipm/template-organisation-management.php`
- `wp-content/themes/ipm/includes/organisation-management-handlers.php`
- `wp-content/themes/ipm/includes/payment-management.php`
- `wp-content/themes/ipm/includes/member-management-handlers.php`
- `wp-content/themes/ipm/includes/enhanced-ajax-handlers.php`

---

## Support

If you encounter any issues after this refactoring:
1. Check the migration ran successfully
2. Verify database schema matches expected structure
3. Check PHP error logs for any issues
4. Test admin functionality thoroughly

For rollback, you can restore the `admin_user_id` column and revert code changes, but this is not recommended after data has been modified.

