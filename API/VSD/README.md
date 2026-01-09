# API/VSD - Deprecated

⚠️ **This directory is deprecated.**

All API endpoints have been moved to `/API/v1/` with improved security and RESTful structure.

## Migration Guide

Old endpoints in this directory are no longer available. Please use the new API v1 endpoints:

| Old Endpoint | New Endpoint |
|--------------|--------------|
| `/API/VSD/documents.php` | `/API/v1/api/documents` |
| `/API/VSD/auth.php?action=login` | `/API/v1/auth/login` |
| `/API/VSD/auth.php?action=status` | `/API/v1/auth/profile` |
| `/API/VSD/user.php` | `/API/v1/api/users/{id}` |
| `/API/VSD/categories.php` | `/API/v1/api/categories` |

## Documentation

- **API Documentation**: See `/API/README.md`
- **API Playground**: `/API/playground.php` (Postman-like interface)

## Security Improvements

The new API v1 includes:
- ✅ API Key authentication with hash storage
- ✅ Prepared statements (SQL injection protection)
- ✅ Rate limiting
- ✅ CORS with whitelist
- ✅ Request logging
- ✅ Input validation

---

**Note**: All files in this directory have been removed as they use outdated patterns and lack proper security measures.

