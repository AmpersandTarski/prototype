#!/bin/bash

echo "🔧 Dev Container Troubleshooting Script"
echo "======================================="

echo ""
echo "1. Testing Docker availability..."
if command -v docker &> /dev/null; then
    echo "✅ Docker CLI is available"
    docker --version
    
    echo ""
    echo "2. Testing Docker Compose..."
    if command -v docker-compose &> /dev/null || docker compose version &> /dev/null; then
        echo "✅ Docker Compose is available"
        docker compose version 2>/dev/null || docker-compose --version
    else
        echo "❌ Docker Compose not found"
    fi
    
    echo ""
    echo "3. Testing Docker socket access..."
    if docker info &> /dev/null; then
        echo "✅ Docker daemon is accessible"
    else
        echo "❌ Cannot connect to Docker daemon"
        echo "   This is expected if running outside dev container"
    fi
else
    echo "❌ Docker CLI not found"
fi

echo ""
echo "4. Environment Information:"
echo "   Current directory: $(pwd)"
echo "   User: $(whoami)"
echo "   Shell: $SHELL"
echo "   PATH: $PATH"

echo ""
echo "5. VS Code Extensions (if in dev container):"
if [ -d "/vscode" ] || [ -n "$VSCODE_IPC_HOOK_CLI" ]; then
    echo "   Running in VS Code dev container context"
else
    echo "   Not in VS Code dev container context"
fi

echo ""
echo "6. Node.js Information:"
if command -v node &> /dev/null; then
    echo "   Node.js: $(node --version)"
    echo "   NPM: $(npm --version)"
else
    echo "   Node.js not found"
fi

echo ""
echo "7. Project Files Check:"
if [ -f "compose.yaml" ]; then
    echo "✅ compose.yaml found"
else
    echo "❌ compose.yaml not found"
fi

if [ -f ".devcontainer/devcontainer.json" ]; then
    echo "✅ devcontainer.json found"
else
    echo "❌ devcontainer.json not found"
fi

echo ""
echo "======================================="
echo "🏁 Test completed!"
