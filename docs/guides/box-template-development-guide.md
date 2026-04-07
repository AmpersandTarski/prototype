# BOX Template Development Guide
This guide shows how to create BOX templates for the Ampersand prototype framework. We use PROPBUTTON as the complete example, progressing from basic concepts to advanced implementation.

Start with the template structure, build the component functionality, and test thoroughly. Use the established patterns for backend communication and error handling to create robust, reusable templates.

## Prerequisites
Creating BOX templates requires understanding three key components: Mustache templates, Angular components, and module registration.
You need basic Angular knowledge and understanding of Ampersand concepts. You must know how BOX templates work and how interfaces generate frontend code. You require access to the prototype framework codebase.

## Overview: The PROPBUTTON Example
PROPBUTTON creates interactive buttons that modify boolean properties. Users click buttons to toggle, set, or clear boolean values. This example demonstrates all key concepts you need to create BOX templates.

### What PROPBUTTON Does
- Creates buttons with custom labels
- Supports three actions: toggle, set, clear
- Modifies boolean properties in the backend
- Updates the interface immediately after changes

## Part 1: Understanding the Architecture

BOX templates consist of three components working together:

### 1. Mustache Template
**Location**: `frontend/src/app/generated/.templates/Box-PROPBUTTON.html`

The "mustache template" is the .html file with placeholders that the Ampersand compiler processes to create your actual Angular components. It's called "Mustache" because the placeholder syntax {{ }} looks like a sideways mustache, though Ampersand uses $variable$ instead.

```html
<!-- Box-PROPBUTTON.html -->
<div $if(isRoot)$*ngIf="resource?.data" $endif$>
    <app-box-prop-button
        crud="$crud$"
        $if(action)$action="$action$"
        $endif$[interfaceComponent]="this"
        [resource]="resource"
        propertyName="$name$"
        [data]="$if(exprIsUni)$[resource.$name$]$else$resource.$name$$endif$"
        tgtResourceType="$target$"
        $if(exprIsUni)$isUni$endif$
        $if(exprIsTot)$isTot$endif$
    >
    </app-box-prop-button>
</div>
```

### 2. Angular Component
**Location**: `frontend/src/app/shared/box-components/box-prop-button/`

The component has three files:
- `box-prop-button.component.ts` - TypeScript logic
- `box-prop-button.component.html` - HTML template
- `box-prop-button.component.scss` - Styling

### 3. Module Registration
**Location**: `frontend/src/app/shared/shared.module.ts`

The component is imported and declared in the shared module.

## Part 2: Quick Start - Template Creation

### Step 1: Create the Mustache Template

Start with the template file. The Ampersand compiler uses this to generate Angular component calls.

**Create**: `frontend/src/app/generated/.templates/Box-YOURTEMPLATE.html`

**Template Pattern**:
```html
<!-- Box-YOURTEMPLATE.html -->
<div $if(isRoot)$*ngIf="resource?.data" $endif$>
    <app-box-your-template
        crud="$crud$"
        [interfaceComponent]="this"
        [resource]="resource"
        propertyName="$name$"
        [data]="$if(exprIsUni)$[resource.$name$]$else$resource.$name$$endif$"
        tgtResourceType="$target$"
        $if(exprIsUni)$isUni$endif$
        $if(exprIsTot)$isTot$endif$
    >
    </app-box-your-template>
</div>
```

### Understanding Template Variables

These variables get substituted during compilation:

- `$crud$` - Permission string (cRud, CRud, etc.)
- `$name$` - Property name from the Ampersand interface  
- `$target$` - Target concept type
- `$if(exprIsUni)$` - Conditional for univalent relations
- `$if(exprIsTot)$` - Conditional for total relations

**PROPBUTTON Addition**: The action parameter
```html
$if(action)$action="$action$"$endif$
```

### Step 2: Test with Ampersand Script

Create a test script to see your template work:

**Location**: `test/projects/yourtemplate-test/model/test.adl`

```ampersand
CONTEXT YourTemplateTest IN ENGLISH

CONCEPT Task "A task for testing"
RELATION taskName[Task*TaskName] [UNI,TOT]
RELATION isCompleted[Task] [PROP]

REPRESENT TaskName TYPE ALPHANUMERIC

POPULATION taskName CONTAINS 
  [ ("task1", "Test task") ]

INTERFACE TestInterface : "_SESSION" ; V[SESSION*Task] cRud BOX<FORM>
  [ "Task Name" : taskName cRud
  , "Complete Task" : I cRud BOX<YOURTEMPLATE>
      [ "label" : TXT "Mark Complete"
      , "property" : isCompleted cRUd
      ]
  ]
```

### Step 3: Compile and Test

```bash
# In your test project directory
docker build -t yourtemplate-test .
docker run -d -p 8080:80 yourtemplate-test
```

Access http://localhost:8080 to see your interface.

## Part 3: Component Implementation

Now we create the Angular component that provides the actual functionality.

### Step 1: Component Structure

Create the component directory:
```
frontend/src/app/shared/box-components/box-prop-button/
├── box-prop-button.component.ts
├── box-prop-button.component.html  
└── box-prop-button.component.scss
```

### Step 2: TypeScript Implementation

**File**: `box-prop-button.component.ts`

```typescript
import { Component, Input } from '@angular/core';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { ObjectBase } from '../../objectBase.interface';

type PropButtonItem = ObjectBase & {
  label: string;
  property: boolean;
};

@Component({
  selector: 'app-box-prop-button',
  templateUrl: './box-prop-button.component.html',
  styleUrls: ['./box-prop-button.component.scss'],
})
export class BoxPropButtonComponent<
  TItem extends PropButtonItem,
  I extends ObjectBase | ObjectBase[],
> extends BaseBoxComponent<TItem, I> {
  @Input() action?: 'toggle' | 'set' | 'clear' = 'toggle';

  handleClick(item: TItem) {
    let value: boolean;

    switch (this.action) {
      case 'set':
        value = true;
        break;
      case 'clear':
        value = false;
        break;
      case 'toggle':
      default:
        value = !item.property;
        break;
    }

    this.interfaceComponent
      .patch(item._path_, [{ op: 'replace', path: 'property', value: value }])
      .subscribe((x) => {
        if (x.isCommitted) {
          this.data = [x.content as any as TItem];
        }
      });
  }
}
```

### Key Implementation Points

1. **Extend BaseBoxComponent** - Provides standard BOX functionality
2. **Define Type Interface** - Specifies expected data structure
3. **Use Generic Types** - Enables proper TypeScript checking
4. **Handle User Actions** - Implements click handlers
5. **Update Backend** - Uses patch() method to modify data
6. **Refresh State** - Updates local data after backend confirmation

### Step 3: HTML Template

**File**: `box-prop-button.component.html`

```html
<div *ngFor="let item of data; let i = index">
    <button pButton (click)="handleClick(item)" label="{{ item.label }}" type="button"></button>
</div>
```

### Step 4: Component Styling

**File**: `box-prop-button.component.scss`

```scss
// Component-specific styles
button {
  margin: 0.25rem;
}
```

### Step 5: Register Component

Add to `frontend/src/app/shared/shared.module.ts`:

```typescript
// Import
import { BoxPropButtonComponent } from './box-components/box-prop-button/box-prop-button.component';

// Add to declarations array
declarations: [
  // ... other components
  BoxPropButtonComponent,
],

// Add to exports array
exports: [
  // ... other components  
  BoxPropButtonComponent,
],
```

## Part 4: Advanced Features

### Custom Actions

PROPBUTTON supports different action types through the action parameter:

```ampersand
-- Toggle action (default)
BOX<PROPBUTTON>
  [ "label" : TXT "Toggle Status"
  , "property" : isActive cRUd
  ]

-- Set action (always true)  
BOX<PROPBUTTON action="set">
  [ "label" : TXT "Activate"
  , "property" : isActive cRUd
  ]

-- Clear action (always false)
BOX<PROPBUTTON action="clear">
  [ "label" : TXT "Deactivate"  
  , "property" : isActive cRUd
  ]
```

### Template Variable Handling

The template handles the action parameter conditionally:

```html
$if(action)$action="$action$"$endif$
```

This generates:
- No action attribute for default toggle behavior
- `action="set"` for set behavior
- `action="clear"` for clear behavior

### Data Binding Patterns

The template uses conditional data binding:

```html
[data]="$if(exprIsUni)$[resource.$name$]$else$resource.$name$$endif$"
```

This handles:
- **Univalent relations**: Single item wrapped in array
- **Non-univalent relations**: Direct array access

### Error Handling

Add robust error handling to your component:

```typescript
this.interfaceComponent
  .patch(item._path_, [{ op: 'replace', path: 'property', value: value }])
  .subscribe({
    next: (result) => {
      if (result.isCommitted) {
        this.data = [result.content as any as TItem];
      }
    },
    error: (error) => {
      console.error('Update failed:', error);
      // Handle error appropriately
    }
  });
```
Since the Ampersand compiler knows nothing about the frontend, it cannot enforce typing constraints on your templates. Therefore, you must generate your own informative error messages, which users will get on runtime.

## Part 5: Testing Your Implementation

### Complete Test Script

**File**: `test/projects/propbutton-unit-test/model/PropButtonTest.adl`

```ampersand
CONTEXT PropButtonTest LABEL "PROPBUTTON Unit Test" IN ENGLISH

CONCEPT Task "A simple task for testing PROPBUTTON functionality"
RELATION taskName[Task*TaskName] [UNI,TOT]
RELATION isCompleted[Task] [PROP]
RELATION isActive[Task] [PROP]

REPRESENT TaskName TYPE ALPHANUMERIC

POPULATION taskName CONTAINS 
  [ ("task1", "Test PROPBUTTON toggle functionality")
  , ("task2", "Test PROPBUTTON set/clear actions")
  ]

INTERFACE PropButtonDemo LABEL "PROPBUTTON Test Interface" : "_SESSION" ; V[SESSION*Task] cRud BOX<FORM>
  [ "Task Name" : taskName cRud
  , "Status Info" : I cRud BOX<FORM>
      [ "Completed" : isCompleted cRud
      , "Active" : isActive cRud
      ]
  , "PROPBUTTON Actions" : I cRud BOX<FORM>
      [ "Toggle Complete" : I cRud BOX<PROPBUTTON>
          [ "label" : TXT "Mark Complete"
          , "property" : isCompleted cRUd
          ]
      , "Activate Task" : I cRud BOX<PROPBUTTON action="set">
          [ "label" : TXT "Activate"  
          , "property" : isActive cRUd
          ]
      , "Deactivate Task" : I cRud BOX<PROPBUTTON action="clear">
          [ "label" : TXT "Deactivate"
          , "property" : isActive cRUd
          ]
      ]
  ]

INTERFACE TaskOverview LABEL "Task Status Overview" : V[SESSION*Task] BOX<TABLE>
  [ "Task" : taskName
  , "Completed" : isCompleted
  , "Active" : isActive
  ]
```

### Testing Scenarios

1. **Toggle Action Test**
   - Click "Mark Complete" button
   - Property should alternate between true/false
   - Verify in Task Status Overview

2. **Set Action Test**
   - Click "Activate" button multiple times
   - Property should always be true
   - Verify persistence across clicks

3. **Clear Action Test**
   - Click "Deactivate" button multiple times
   - Property should always be false
   - Verify persistence across clicks

### Compilation Process

Your template goes through this process:

1. **Ampersand Compilation** - Processes `.adl` files, generates TypeScript
2. **Template Processing** - Substitutes variables in Mustache templates
3. **Angular Compilation** - Compiles TypeScript to JavaScript

## Part 6: Common Patterns and Best Practices

### Component Design Patterns

**1. Type Safety**
```typescript
type YourComponentItem = ObjectBase & {
  requiredField: string;
  optionalField?: boolean;
};
```

**2. Input Validation**
```typescript
@Input() customParameter?: string;

ngOnInit() {
  if (this.customParameter && !this.isValidParameter(this.customParameter)) {
    console.warn('Invalid parameter:', this.customParameter);
  }
}
```

**3. Naming Conventions**
- Component selector: `app-box-your-template`
- Template file: `Box-YOURTEMPLATE.html`
- Component class: `BoxYourTemplateComponent`

### Backend Communication

**PROPBUTTON Pattern**:
```typescript
this.interfaceComponent
  .patch(item._path_, [{ op: 'replace', path: 'property', value: newValue }])
  .subscribe((result) => {
    if (result.isCommitted) {
      this.data = [result.content as any as TItem];
    }
  });
```

**Key Points**:
- Use `patch()` for data modifications
- Check `isCommitted` before updating local state
- Update `this.data` to refresh the interface

### Template Variable Best Practices

**Standard Variables** (always include):
```html
crud="$crud$"
[interfaceComponent]="this"
[resource]="resource"
propertyName="$name$"
tgtResourceType="$target$"
```

**Multiplicity Handling**:
```html
[data]="$if(exprIsUni)$[resource.$name$]$else$resource.$name$$endif$"
$if(exprIsUni)$isUni$endif$
$if(exprIsTot)$isTot$endif$
```

**Custom Parameters**:
```html
$if(yourParameter)$yourParameter="$yourParameter$"$endif$
```

## Part 7: Troubleshooting

### Common Issues

**Template Not Found**
- Check file naming: `Box-PROPBUTTON.html` (exact case)
- Verify location: `frontend/src/app/generated/.templates/`
- Confirm compilation completed successfully

**Variables Not Substituted**  
- Check syntax: `$variableName$` (with dollar signs)
- Verify conditional syntax: `$if(condition)$...$endif$`
- Ensure variables exist in compilation context

**Component Not Working**
- Verify registration in `shared.module.ts`
- Check component selector matches template
- Confirm component extends `BaseBoxComponent`
- Check property names and data binding

**Backend Communication Fails**
- Verify CRUD permissions: property needs `cRUd` (capital U)
- Check property path in patch operation
- Confirm backend API endpoints work
- Use browser developer tools to inspect network requests

### Debugging Steps

1. **Check Template Generation**
   ```bash
   # Look for generated files
   ls frontend/src/app/generated/
   ```

2. **Verify Component Registration**
   ```typescript
   // In shared.module.ts, confirm:
   import { BoxPropButtonComponent } from './box-components/box-prop-button/box-prop-button.component';
   // ... in declarations and exports arrays
   ```

3. **Test Backend API**
   ```javascript
   // In browser console
   fetch('/api/v1/resource/Task/task1', {method: 'GET'})
     .then(r => r.json())
     .then(console.log)
   ```

4. **Inspect Generated Code**
   Look at `frontend/src/app/generated/project.module.ts` for your interface components.
