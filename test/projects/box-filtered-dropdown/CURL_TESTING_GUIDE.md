# Ampersand Project Testing Guide Using cURL

## Overview
This guide provides comprehensive instructions for testing Ampersand prototype applications using cURL commands instead of browser-based testing. This approach is useful for:
- Automated testing scripts
- CI/CD pipelines
- API validation
- Debugging backend issues
- Testing without GUI dependencies

## Basic Testing Workflow

### 1. Stack Initialization and Health Check

First, ensure your containers are running and wait for full initialization:

```bash
# Start the stack
docker compose up -d --build

# Wait for services to be ready (inspired by NVWA script approach)
function wait_for_stack_ready() {
    local max_attempts=60
    local attempt=1
    
    echo "Waiting for complete stack (API + Database) to be ready..."
    
    while [ $attempt -le $max_attempts ]; do
        # Test 1: Basic API connectivity
        if ! curl -s "http://localhost/api/v1/app/navbar" >/dev/null 2>&1; then
            if [ $((attempt % 10)) -eq 0 ]; then
                echo "API not ready yet... (attempt $attempt/$max_attempts)"
            fi
            sleep 2
            ((attempt++))
            continue
        fi
        
        # Test 2: Check if application needs installation
        response=$(curl -s "http://localhost/api/v1/admin/installer?defaultPop=true" 2>/dev/null)
        if echo "$response" | grep -q '"message": *"Application successfully reinstalled"'; then
            echo "✅ Complete stack is operational!"
            return 0
        elif echo "$response" | grep -q "Connection refused\|doesn't exist"; then
            if [ $((attempt % 10)) -eq 0 ]; then
                echo "Database not ready yet... (attempt $attempt/$max_attempts)"
            fi
        else
            # API works, check if already installed
            if curl -s "http://localhost/api/v1/app/navbar" | grep -q "navs"; then
                echo "✅ Stack already operational!"
                return 0
            fi
        fi
        
        sleep 2
        ((attempt++))
    done
    
    echo "❌ Stack did not become operational after $((max_attempts * 2)) seconds"
    return 1
}
```

### 2. Application Installation

```bash
# Install/reinstall the application with default population
curl -s "http://localhost/api/v1/admin/installer?defaultPop=true"

# Expected successful response:
# {
#     "errors": [],
#     "warnings": [],
#     "infos": [],
#     "successes": [{"message": "Application successfully reinstalled"}],
#     "invariants": [],
#     "signals": []
# }
```

### 3. Application Structure Discovery

```bash
# Get navigation structure and available interfaces
curl -s "http://localhost/api/v1/app/navbar" | jq '.'

# This returns the main navigation structure showing available interfaces
# Look for "navs" array containing interface definitions
```

### 4. Basic API Health Checks

```bash
# Test basic connectivity
curl -s -o /dev/null -w "%{http_code}\n" "http://localhost/api/v1/app/navbar"
# Expected: 200

# Test if installation is required
curl -s "http://localhost/api/v1/admin/installer" | grep -q "reinstalled\|already installed"
```

## Common API Endpoints

### Core Application Endpoints
- `GET /api/v1/app/navbar` - Navigation structure
- `GET /api/v1/admin/installer` - Installation status
- `POST /api/v1/admin/installer?defaultPop=true` - Install/reinstall with default data

### Resource Access Patterns
- `GET /api/v1/resource/{ConceptName}` - Get all atoms of a concept
- `GET /api/v1/resource/{ConceptName}/{atomId}` - Get specific atom
- `POST /api/v1/resource/{ConceptName}` - Create new atom
- `PUT /api/v1/resource/{ConceptName}/{atomId}` - Update atom
- `DELETE /api/v1/resource/{ConceptName}/{atomId}` - Delete atom

### Interface Access
- `GET /api/v1/interface/{InterfaceName}` - Access specific interface
- `POST /api/v1/interface/{InterfaceName}` - Interact with interface

## Error Response Patterns

### Common Error Types and Solutions

1. **Database Connection Issues**
   ```json
   {
     "error": 500,
     "msg": "Cannot connect to the databse: Access denied..."
   }
   ```
   **Solution**: Check database permissions, restart with fresh volumes

2. **Application Not Installed**
   ```json
   {
     "error": 500,
     "msg": "Table 'database_name.SESSION' doesn't exist. Try reinstalling the application"
   }
   ```
   **Solution**: Run installer endpoint

3. **Invalid Database Names**
   ```json
   {
     "msg": "You have an error in your SQL syntax... near '-database_name'"
   }
   ```
   **Solution**: Use underscores instead of hyphens in database names

## Testing Specific Functionality

### For FilteredDropdown Testing (Example)

```bash
# Test navigation contains filtered dropdown interfaces
curl -s "http://localhost/api/v1/app/navbar" | grep -i "filtered"

# Test specific interface access (adjust interface name as needed)
# Note: Direct resource access may require proper authentication/session
```

## Debugging Tips

### 1. Check Container Status
```bash
docker compose ps
docker compose logs [service-name]
```

### 2. Database Connectivity
```bash
# Test database connection via PhpMyAdmin (if available)
curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8081"
```

### 3. Verbose cURL for Debugging
```bash
# Add verbose flag to see full request/response
curl -v "http://localhost/api/v1/app/navbar"

# Add timing information
curl -w "Time: %{time_total}s\n" -s "http://localhost/api/v1/app/navbar"
```

### 4. JSON Response Formatting
```bash
# Pretty print JSON responses
curl -s "http://localhost/api/v1/app/navbar" | jq '.'

# Extract specific fields
curl -s "http://localhost/api/v1/app/navbar" | jq '.navs[].label'
```

## Best Practices

1. **Always wait for stack readiness** before running tests
2. **Check HTTP status codes** in addition to response content
3. **Handle errors gracefully** and provide meaningful feedback
4. **Use jq for JSON parsing** when available
5. **Test both success and failure scenarios**
6. **Document expected responses** for your specific application

## Common Issues and Solutions

### Database Name Issues
- ❌ `filtered-dropdown_test` (hyphens cause SQL syntax errors)
- ✅ `filtered_dropdown_test` (underscores work correctly)

### Volume Persistence
- Use `docker compose down -v` to reset database state when needed
- Fresh volumes ensure clean installation

### Timing Issues
- Always implement proper wait mechanisms
- Database initialization takes time after container startup
- API may be responsive before database is ready

## Example Complete Test Script

```bash
#!/bin/bash
set -e

echo "🧪 Starting Ampersand Project Testing"

# Function definitions
wait_for_stack_ready() {
    # [Implementation from above]
}

# Test execution
cd /path/to/your/project
docker compose up -d --build

wait_for_stack_ready

echo "✅ Testing completed successfully!"
echo "🌐 Application available at: http://localhost"
```

This guide provides a foundation for testing any Ampersand project using cURL commands, avoiding the need for browser-based testing while ensuring comprehensive validation of your application's functionality.
