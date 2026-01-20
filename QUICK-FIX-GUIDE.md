# Quick Fix: "Failed to start migration" Error

## You clicked "Migrate Products" and got an error. Here's what to do:

---

## ⚠️ What the Error Means

The error tells you EXACTLY what's wrong. Read it carefully!

You'll see one of these:

### Error Type 1: Database Password Wrong
```
• Db: Database connection failed: Access denied for user 'luciaand_lucia'@'localhost'
```

**This means:** Your database password is incorrect.

### Error Type 2: API Permission Issue
```
• Api: Access denied (403). The OAuth consumer does not have permission...
```

**This means:** Your API integration lacks proper permissions.

### Error Type 3: Both Wrong
```
• Api: Access denied...
• Db: Database connection failed...
```

**This means:** Both need to be fixed.

---

## 🔧 How to Fix in 5 Minutes

### Fix Database Password (Recommended)

**Step 1: Find correct password**
```bash
# SSH into your Magento server and run:
cat app/etc/env.php | grep -A 5 "db"
```

You'll see:
```
'host' => 'localhost',
'dbname' => 'your_database_name',
'username' => 'your_username',
'password' => 'THE_CORRECT_PASSWORD',  ← Copy this
```

**Step 2: Update WordPress**
1. Go to: WordPress Admin → Magento → Migrator → Settings
2. Find "Database Password" field
3. Paste the correct password
4. Click "Save Changes"

**Step 3: Test**
1. Click "Test Connection" button
2. Should say "Connection successful"

**Step 4: Migrate**
1. Click "Migrate Products"
2. Progress will appear! ✓

---

## 📊 What You'll See After Fix

### When Migration Works:

```
┌─────────────────────────────────────────┐
│  Migration in Progress                  │
├─────────────────────────────────────────┤
│  Type: Products                         │
│  Current: Migrating: product-sku-123    │
│  Time Remaining: 3 minutes              │
│                                         │
│  ████████████░░░░░░░░░░░░               │
│  47%                                    │
│                                         │
│  47% Complete    94 of 200              │
│  Success Rate:   98%                    │
│                                         │
│  Total: 200  Processed: 94              │
│  Successful: 92  Failed: 2              │
│                                         │
│  [Cancel Migration]                     │
└─────────────────────────────────────────┘
```

---

## 🆘 Still Need Help?

### Run Diagnostic:
```bash
cd /workspace/wp-content/plugins/magento-wordpress-migrator
php test-migration-start.php
```

This will tell you exactly what's wrong.

### Check Your Credentials:

**Database credentials in Magento:**
- File: `app/etc/env.php`
- Look for: `db` section

**API credentials in Magento:**
- Go to: Admin → System → Integrations
- Check your integration permissions

---

## ✅ Checklist

Before clicking "Migrate Products":

- [ ] Database password is correct
- [ ] Clicked "Test Connection" → Works
- [ ] WooCommerce is active
- [ ] Have backup of WordPress database

Then:
- [ ] Click "Migrate Products"
- [ ] Watch progress appear immediately
- [ ] Monitor as it migrates 0% → 100%

---

## 🎯 Bottom Line

**The error message is your friend.** It tells you exactly what to fix.

1. **Database password wrong?** Update it in Settings
2. **API permissions wrong?** Fix them in Magento Admin
3. **Not sure?** Run the diagnostic script

Once credentials are correct, migration starts immediately and shows real-time progress!

---

**Need more details?** See `MIGRATION-STARTUP-FIXED.md`
