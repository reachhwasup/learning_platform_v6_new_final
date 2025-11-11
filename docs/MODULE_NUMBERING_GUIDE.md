# Module Numbering Guide

## How Module Display Numbers Work

### Overview
The learning platform now displays modules to users starting from **Module 1**, regardless of the `module_order` value in the database. This allows you to create an Introduction module with `module_order = 0`, and it will be displayed as **Module 1** to users.

---

## For Administrators

### Creating an Introduction Module

When creating your first module (Introduction):

1. **Module Title:** `Introduction to Information Security` (or any title you prefer)
2. **Display Order:** `0` ← **Enter zero here**
3. **Description:** Your introduction content
4. **Upload video and materials as needed**

### Creating Regular Learning Modules

For all subsequent modules:

1. **Module 1 (Phishing):** Display Order = `1`
2. **Module 2 (Password Security):** Display Order = `2`
3. **Module 3 (Social Engineering):** Display Order = `3`
4. **Module 4 (Data Protection):** Display Order = `4`
... and so on

---

## What Users See

Even though you set display orders as `0, 1, 2, 3...`, users will see:

### Dashboard View:
- **Introduction to Information Security** ← (display_order = 0, shows only title)
- **Module 1:** Phishing Awareness ← (display_order = 1)
- **Module 2:** Password Security ← (display_order = 2)
- **Module 3:** Social Engineering ← (display_order = 3)

### Benefits:
✅ Introduction module shows only the title (no "Module" prefix)  
✅ Regular modules numbered starting from Module 1  
✅ Introduction appears first and stands out  
✅ Professional presentation  

---

## Technical Implementation

### Files Modified:
- `dashboard.php` - Added `display_number` calculation
- `view_module.php` - Added `display_number` for page titles
- `materials.php` - Added `display_number` for PDF materials
- `admin/manage_modules.php` - Added helpful tip for administrators

### How It Works:
```php
// The system checks if module_order is 0 (introduction)
$module['is_introduction'] = ($module['module_order'] == 0);

// Calculate display number, accounting for introduction
if ($is_introduction) {
    $display_number = 1; // But won't show "Module 1:"
} else {
    $display_number = $index + 1 - $intro_count; // Subtract intro modules
}

// Display logic
if ($module['is_introduction']) {
    echo $module['title']; // Just "Introduction to Information Security"
} else {
    echo "Module " . $display_number . ": " . $module['title']; // "Module 1: Phishing"
}
```

---

## Example Setup

### Recommended Module Order for 500 Staff:

| Display Order | Display to Users | Module Title |
|---------------|------------------|--------------|
| 0 | **Introduction to Information Security** | Introduction to Information Security |
| 1 | **Module 1:** Phishing and Email Security | Phishing and Email Security |
| 2 | **Module 2:** Password Management | Password Management |
| 3 | **Module 3:** Social Engineering | Social Engineering |
| 4 | **Module 4:** Secure Web Browsing | Secure Web Browsing |
| 5 | **Module 5:** Data Protection and Privacy | Data Protection and Privacy |
| 6 | **Module 6:** Mobile Device Security | Mobile Device Security |
| 7 | **Module 7:** Physical Security | Physical Security |
| 8 | **Module 8:** Incident Reporting | Incident Reporting |
| 9 | **Module 9:** Best Practices Summary | Best Practices Summary |

---

## Important Notes

⚠️ **Always use sequential numbers** starting from 0  
⚠️ **Don't skip numbers** (e.g., don't go from 2 to 5)  
⚠️ **Don't use duplicate numbers** for different modules  

✅ **Correct:** 0, 1, 2, 3, 4, 5...  
❌ **Wrong:** 0, 1, 3, 5, 7... (skipping numbers)  
❌ **Wrong:** 1, 1, 2, 3... (duplicate 1)  

---

## Need to Reorder Modules?

If you need to change the order:

1. Go to **Admin Panel → Manage Modules**
2. Click **Edit** on the module
3. Change the **Display Order** value
4. **Save** the module
5. The system will automatically re-sort and renumber for display

---

## Questions?

If you need to add modules between existing ones:

### Example: Add new module between current Module 2 and 3

**Before:**
- Display Order 0 = Introduction to Information Security
- Display Order 1 = Module 1: Phishing
- Display Order 2 = Module 2: Password Security

**To insert "Email Security" between Phishing and Password:**

1. Edit "Password Security" → Change Display Order from `2` to `3`
2. Edit any modules after it (increase their order by 1)
3. Create new module "Email Security" → Set Display Order to `2`

**After:**
- Display Order 0 = Introduction to Information Security
- Display Order 1 = Module 1: Phishing
- Display Order 2 = Module 2: Email Security ← **New module**
- Display Order 3 = Module 3: Password Security

---

**Created:** October 28, 2025  
**Platform Version:** 6.0  
**Last Updated:** October 28, 2025
