# VS Code Configuration for Code Coverage Preview

This directory contains VS Code workspace settings that include optional extension recommendations and configurations for previewing code coverage reports.

## Recommended Extensions

When you first open this project in VS Code, you'll be prompted to install the following recommended extensions:

### 1. Live Server (ritwickdey.liveserver)
- **Purpose**: Provides a local development server with live reload capability
- **Use Case**: Preview HTML coverage reports with proper HTTP serving
- **Installation**: Click "Install" when prompted, or install manually from the Extensions marketplace

### 2. Coverage Gutters (ryanluker.vscode-coverage-gutters)
- **Purpose**: Displays test coverage information directly in the editor
- **Use Case**: Shows coverage indicators in the gutter and highlights uncovered lines
- **Installation**: Click "Install" when prompted, or install manually from the Extensions marketplace

## Setup Instructions

### Step 1: Install Extensions
1. Open the project in VS Code
2. When prompted, click "Install" for the recommended extensions
3. Alternatively, go to Extensions (Ctrl+Shift+X) and search for:
   - "Live Server" by Ritwick Dey
   - "Coverage Gutters" by ryanluker

### Step 2: Enable Optional Settings
After installing the extensions, uncomment the relevant settings in `.vscode/settings.json`:

**For Live Server:**
```json
"liveServer.settings.root": "/frontend/coverage",
"liveServer.settings.CustomBrowser": "chrome",
"liveServer.settings.port": 5500,
"liveServer.settings.host": "localhost",
"liveServer.settings.donotShowInfoMsg": true,
"liveServer.settings.donotVerifyTags": true,
"liveServer.settings.mount": [
    ["/coverage", "./frontend/coverage"]
],
"liveServer.settings.file": "index.html",
"liveServer.settings.wait": 1000,
"liveServer.settings.fullReload": false
```

**For Coverage Gutters:**
```json
"coverage-gutters.coverageFileNames": [
    "lcov.info",
    "cov.xml",
    "coverage.xml",
    "jacoco.xml",
    "coverage.lcov"
],
"coverage-gutters.coverageBaseDir": "./frontend",
"coverage-gutters.showLineCoverage": true,
"coverage-gutters.showRulerCoverage": true,
"coverage-gutters.showGutterCoverage": true
```

## Usage

### Generating Coverage Reports
```bash
cd frontend
npm run test:coverage
```

This generates coverage reports in `frontend/coverage/` directory.

### Viewing Coverage Reports

**Method 1: Live Server**
1. Right-click on `frontend/coverage/index.html`
2. Select "Open with Live Server"
3. Coverage report opens at `http://localhost:5500`

**Method 2: Coverage Gutters**
1. Open any source file in the editor
2. Press Ctrl+Shift+P and run "Coverage Gutters: Display Coverage"
3. Coverage indicators appear in the editor gutter

### Benefits

- **Live Reload**: Coverage reports update automatically when tests are re-run
- **Proper HTTP Serving**: Avoids CORS issues with file:// protocol
- **Visual Indicators**: See coverage directly in your code editor
- **Interactive Reports**: Click through coverage reports to see detailed information

## File Associations

The configuration includes file associations for coverage files:
- `*.lcov` files are treated as plain text for better readability
- Coverage HTML files are properly associated with HTML language support

## Troubleshooting

### Extensions Not Installing
- Manually install from Extensions marketplace (Ctrl+Shift+X)
- Restart VS Code after installation

### Coverage Not Displaying
- Ensure coverage reports exist in `frontend/coverage/`
- Run `npm run test:coverage` to generate reports
- Check that Coverage Gutters settings are uncommented

### Live Server Not Working
- Verify Live Server extension is installed and enabled
- Check that port 5500 is not in use by another application
- Try refreshing the browser or restarting Live Server

## Optional Configuration

All extension settings are commented out by default to avoid conflicts. Uncomment only the settings you need for your workflow.
