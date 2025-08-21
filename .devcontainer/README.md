# Ampersand Prototype Dev Container

This dev container provides a consistent development environment for the Ampersand Prototype project, eliminating dependency issues like the ICU library problems on macOS.

## What's Included

- **Ampersand Framework**: Complete Ampersand prototype framework
- **Node.js 18**: For frontend development and build tools
- **Angular CLI**: For Angular development
- **Git**: Version control
- **Development Tools**: vim, nano, htop, tree, jq
- **Docker Access**: Can run docker commands from within the container

## How to Use

### 1. **Open in Dev Container**
1. Install the "Dev Containers" extension in VS Code
2. Open this project in VS Code
3. Press `Cmd+Shift+P` (Mac) or `Ctrl+Shift+P` (Windows/Linux)
4. Type "Dev Containers: Reopen in Container"
5. Select it and wait for the container to build

### 2. **Your New Workflow**
Once in the dev container, your terminal will be connected to the container with all tools available:

```bash
# Check that everything is installed
node --version          # Should show Node.js 18.x
npm --version          # Should show npm version
ng version             # Should show Angular CLI

# Your normal workflow now works inside the container
docker compose up -d --build
./generate.sh feature-254-filtered-dropdown main.adl

# Frontend development
cd frontend
npm install
npm run build
ng serve  # For development server
```

### 3. **Benefits**
- ✅ **No more ICU library issues** - Everything runs in isolated container
- ✅ **Consistent environment** - Same setup for all team members
- ✅ **No local Node.js required** - Node.js runs inside container
- ✅ **Docker access** - Can still run docker commands
- ✅ **Port forwarding** - Access apps on localhost as usual
- ✅ **File sync** - Your local files are mounted in the container

### 4. **Ports Available**
- **Port 80**: Ampersand Prototype application
- **Port 8080**: phpMyAdmin
- **Port 4200**: Angular development server
- **Port 3000**: Additional development server

### 5. **File Structure**
```
/var/www/          # Your project root (mounted from local)
├── frontend/      # Angular frontend
├── backend/       # PHP backend  
├── test/          # Test projects
└── generate.sh    # Build script
```

## Troubleshooting

### Container Won't Start
- Make sure Docker Desktop is running
- Try: "Dev Containers: Rebuild Container"

### Permission Issues
- The container runs as root to avoid permission problems
- Files created in container will be owned by your local user

### Can't Access Ports
- Check VS Code's "Ports" tab
- Manually forward ports if needed

## Team Benefits

This setup ensures that:
- **Everyone has the same environment** regardless of their local setup
- **No more "works on my machine" issues**
- **Easy onboarding** for new team members
- **Isolated dependencies** don't conflict with local tools
