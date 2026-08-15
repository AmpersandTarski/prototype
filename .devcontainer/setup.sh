#!/bin/bash
echo "🔧 Setting up development environment..."

# skip all changes to the dummy file project.module.ts. 
git update-index --skip-worktree frontend/src/app/generated/project.module.ts
echo "✅ Generated and compiler-modified files will now be ignored for git"

echo "🎉 Development environment setup complete!"
