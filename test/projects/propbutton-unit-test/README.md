# PROPBUTTON Unit Test

This test validates the PROPBUTTON template functionality as documented in `/docs/reference-material/propbutton-template.md`.

## Test Scope

**What this test validates:**
- PROPBUTTON template compilation
- All three action types: toggle, set, clear
- Prescribed field names: `"label"` and `"property"`
- Boolean property modification behavior
- Frontend button rendering and functionality

## Test Structure

```
propbutton-unit-test/
├── model/
│   └── PropButtonTest.adl     # Minimal test script
├── Dockerfile                 # Compilation setup
└── README.md                  # This file
```

## Running the Test

### Build and Run
```bash
cd /Users/sjo00577/git/PrototypeFramework/test/projects/propbutton-unit-test
docker build -t propbutton-test .
docker run -d -p 8080:80 --name propbutton-test propbutton-test
```

### Access Test Interface
Open browser to: http://localhost:8080

### Available Interfaces
- **PROPBUTTON Test Interface**: Main test interface with all button types
- **Task Status Overview**: Table view to verify property changes

## Manual Validation Steps

### Phase 1: Basic PROPBUTTON Function
1. **Compilation**: Verify Docker build completes without errors
2. **Interface rendering**: Confirm both interfaces load correctly  
3. **Button display**: Check all three buttons render with correct labels
4. **Property display**: Verify status fields show current property values

### Phase 2: Action Type Validation

#### Toggle Action Test
- **Button**: "Mark Complete"
- **Property**: `isCompleted`  
- **Expected behavior**: Clicking alternates between true/false
- **Verification**: Status in overview table updates correctly

#### Set Action Test
- **Button**: "Activate"
- **Property**: `isActive`
- **Expected behavior**: Always sets property to true
- **Verification**: Multiple clicks keep property true

#### Clear Action Test
- **Button**: "Deactivate"  
- **Property**: `isActive`
- **Expected behavior**: Always sets property to false
- **Verification**: Multiple clicks keep property false

## Expected Results

### Successful Test Indicators
✅ Docker build completes without compilation errors
✅ Both interfaces load and display correctly
✅ All buttons render with correct labels
✅ Toggle button alternates property between true/false
✅ Set button always makes property true
✅ Clear button always makes property false
✅ Property changes reflect immediately in overview table
✅ No console errors in browser developer tools

### Test Data
The test uses two tasks:
- `"Test PROPBUTTON toggle functionality"`
- `"Test PROPBUTTON set/clear actions"`

Both start with `isCompleted: false` and `isActive: false`.

## Cleanup
```bash
docker stop propbutton-test
docker rm propbutton-test
docker rmi propbutton-test
```

## Documentation Validation

This test verifies that all examples in the PROPBUTTON documentation work as described:
- Prescribed field names are recognized correctly
- Action parameter syntax is valid
- CRUD permissions function properly
- Generated Angular components match expected behavior
