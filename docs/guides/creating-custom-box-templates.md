# Creating Custom BOX Templates

This guide provides step-by-step instructions for creating custom BOX templates in the Ampersand prototype framework. We'll use the FILTEREDDROPDOWN template as a practical example throughout this tutorial.

## Prerequisites

You need basic understanding of HTML and Angular components before you begin. You need to understand the architecture of an Ampersand application. You must also know Ampersand concepts like BOX, interfaces, and relations. You require access to the prototype framework codebase. You must understand the template variable system that uses placeholders like `$name$` and `$label$`. You must know how to build an run an Ampersand script.

## Step 1: Identify Your Use Case

### Determine if You Need a Template

You should evaluate whether existing BOX templates meet your requirements. The framework provides FORM, TABLE, TABS, and RAW templates for common use cases. You need a custom template when you require interaction patterns that these standard templates cannot provide. Consider whether you will reuse this template across multiple interfaces to justify the development effort.

### Example: The FILTEREDDROPDOWN Use Case

**Problem**: Users needed dropdown selections that show only business-rule-filtered options, rather than all available options.

**Solution**: Create a FILTEREDDROPDOWN template that integrates filtering logic with the object selection component.

## Step 2: Design Your Template

### Define the Template Behavior

Let us define your template behavior first. Specify what data your template receives as input. Define what the user sees and how they interact with the interface. Document the business rules and constraints that apply to your template.

### FILTEREDDROPDOWN Example Design

```
Input: 
- Resource object with property data
- Select options (filtered list)
- Standard BOX properties (name, label, CRUD, etc.)

Output:
- Dropdown showing only filtered options
- Search capability within filtered options
- Standard CRUD operations on selections

Constraints:
- Respects UNI/TOT multiplicity constraints
- Maintains type safety with existing atomic components
```

## Step 3: Create the Template File

BOX templates follow the pattern: `Box-{TEMPLATENAME}.html`

Example: `Box-FILTEREDDROPDOWN.html`

If your new template is meant for all Ampersand projects, place it in: `frontend/src/app/generated/.templates/`. This is a change in the prototype framework, which requires a pull-request if you want it to take it to production. If you only want to add the template to your project, make customization section in that project and place it there. In that case, adapt the dockerfile of your project to transfer your template to the front-end at build-time, so it remains project-specific and does not add to the cognitive load of other users.

**Note**: This location is for the generated templates. During development, templates are typically stored in the main Ampersand templates folder and copied during compilation.

### Basic Template Structure

```html
<!--Template comment explaining purpose-->
<app-{component-type}
    [resource]="resource"
    [interfaceComponent]="this"
    [property]="resource.$name$"
    propertyName="$name$"
    label="$label$"
    crud="$crud$"
    placeholder="Add $target$"
    tgtResourceType="$target$"
    $if(exprIsUni)$isUni$endif$
    $if(exprIsTot)$isTot$endif$
>
</app-{component-type}>
```

## Step 4: Implement Your Template

### FILTEREDDROPDOWN Implementation

```html
<!--just use atomic object component, the select property will be detected inside the atomic object code-->
<app-atomic-object
    [resource]="resource"
    [interfaceComponent]="this"
    [property]="resource.$name$"
    propertyName="$name$"
    label="$label$"
    crud="$crud$"
    placeholder="Add $target$"
    tgtResourceType="$target$"
    [selectOptions]="resource.$name$"
    $if(exprIsUni)$isUni$endif$
    $if(exprIsTot)$isTot$endif$
></app-atomic-object>
```

### Key Implementation Decisions

1. The FILTEREDDROPDOWN reuses `app-atomic-object` rather than creating a new component, to preserve all standard BOX properties.
2. The `[selectOptions]` property provides the filtered data.

## Step 5: Handle Template Variables
The front-end takes information the Ampersand compiler has provided and stores it in variables to work with.
```html
<!-- Required for all BOX templates -->
propertyName="$name$"          <!-- Property identifier -->
label="$label$"                <!-- Display label -->
crud="$crud$"                  <!-- Permission string (e.g., "cRud") -->
placeholder="Add $target$"     <!-- User-friendly placeholder text -->
tgtResourceType="$target$"     <!-- Target concept type -->

<!-- Handle multiplicity constraints -->
$if(exprIsUni)$isUni$endif$   <!-- Univalent (max 1 target per source) -->
$if(exprIsTot)$isTot$endif$   <!-- Total (min 1 target per source) -->
```
For specialized templates, you may need custom variables:
```html
<!-- Example: Custom filtering property -->
[selectOptions]="resource.$name$"  <!-- Uses the same property name for filtering -->
```

## Step 6: Test Your Template

### Create Test Ampersand Script

Create a simple `.adl` file to test your template:

```ampersand
-- Test script for FILTEREDDROPDOWN
CONTEXT FilteredDropdownTest IN ENGLISH

CONCEPT Employee ""
CONCEPT Project ""

RELATION projectMember[Project*Employee]
RELATION eligibleEmployees[Employee] [PROP]

-- Test interface using your template
INTERFACE ProjectForm : "_SESSION"[SESSION]; V[SESSION*Project] cRud BOX<FORM>
  [ "Assign employee" : projectMember cRud BOX<FILTEREDDROPDOWN>
    [ select : eligibleEmployees ]
  ]
```

### Test Different Scenarios

You must create test cases for different scenarios. Test default behavior without constraints. Test UNI constraints that limit maximum selection to one item. Test TOT constraints that require minimum one selection. Test UNI+TOT constraints that enforce exactly one selection.

### Example Test Files

```
test/projects/custom-template-test/model/
├── main.adl
├── basic-test.adl
├── uni-constraint-test.adl
├── tot-constraint-test.adl
└── uni-tot-constraint-test.adl
```

## Step 7: Integration and Compilation

### Understanding the Two-Stage Compilation Process

The Ampersand prototype framework uses a two-stage compilation process:

1. **Ampersand Compilation**: Generates TypeScript components and templates.
2. **Angular Compilation**: Compiles TypeScript to JavaScript for the browser

### Template Placement for Compilation

Templates move through several locations during development and compilation. You place templates in your project's `templates/` folder during development. You must copy templates to `/var/www/templates/` before Ampersand compilation. The Ampersand compiler places processed templates in `frontend/src/app/generated/.templates/`. You add copy commands to your Dockerfile for Docker integration.

```dockerfile
# Copy custom templates before running Ampersand compiler
RUN cp -r -v /usr/local/project/templates /var/www/
```

### The Ampersand Compilation Stage

Run the Ampersand compiler to generate your interfaces:

```bash
ampersand proto /usr/local/project/script.adl \
  --proto-dir /var/www \
  --verbose
```

**What happens during Ampersand compilation:**
- Processes your `.adl` script files
- Generates `project.module.ts` with all interface components
- Populates the `.templates/` folder with processed HTML templates
- Creates TypeScript component declarations
- Substitutes template variables (`$name$`, `$label$`, etc.) with actual values

### Understanding the Generated project.module.ts

The `frontend/src/app/generated/project.module.ts` file is completely generated by Ampersand:

```typescript
// This file will be overwritten by the Ampersand compiler
// It contains:
// - All generated interface components
// - Component declarations and imports
// - Angular module definitions
// - Routing configurations
```

**Note**: You may see a "dummy" `project.module.ts` file before compilation. This exists as a placeholder and gets completely overwritten by the Ampersand compiler - this is normal behavior.

### The Angular Compilation Stage

After Ampersand generates the TypeScript code, Angular's own compiler:
- Compiles TypeScript to JavaScript
- Bundles all components and modules
- Creates the final application that runs in the browser
- Optimizes the code for production deployment

## Step 8: Validate and Debug

### Check Generated Files

You must verify that your template processed correctly after compilation. Look for your interface components in `project.module.ts` to check the generated module. Confirm the template variables were substituted correctly to verify template output. Use the generated interface to test functionality and ensure it works as expected.

### Common Issues and Solutions

| Problem | Symptom | Solution |
|---------|---------|----------|
| Template not found | Compilation error | Check file naming and location |
| Variables not substituted | Literal `$name$` in output | Verify template variable syntax |
| Component errors | Angular compilation failure | Check component property bindings |
| Missing functionality | Template renders but doesn't work | Verify underlying component support |
| "Dummy" module confusion | Seeing placeholder project.module.ts | This is normal - Ampersand will overwrite it |
| Two-stage compilation failure | TypeScript errors after Ampersand success | Check generated TypeScript for Angular compatibility |
| Template selector mismatch | Component not rendering | Verify selector matches component decorator |

### Advanced Troubleshooting

#### Understanding the "Dummy" project.module.ts

You may encounter a placeholder file at `frontend/src/app/generated/project.module.ts` that contains:

```typescript
// This dummy module will be overwritten by the compiler
// Don't modify this file manually
```

**This is completely normal behavior.** The Ampersand compiler needs this file to exist for Angular's module system, but it gets completely replaced during compilation. Do not try to edit or fix this file - it's intentionally minimal.

#### Debugging Template Variable Substitution

If your template variables aren't being substituted correctly:

1. **Check template syntax**: Ensure you're using `$variableName$` format
2. **Verify variable availability**: Not all variables are available in all contexts
3. **Test with simple variables first**: Start with `$name$` and `$label$` before adding complex logic
4. **Check Ampersand compilation logs**: Look for variable substitution warnings

#### Component Selector Debugging

If your template renders but the wrong component appears:

```html
<!-- Template uses this selector -->
<app-atomic-object></app-atomic-object>
```

```typescript
// Component must have matching selector
@Component({
  selector: 'app-atomic-object',  // Must match template exactly
  templateUrl: './atomic-object.component.html'
})
```

#### Inheritance and Base Component Issues

When extending components, ensure you're inheriting from the correct base:

```typescript
// For atomic components
export class CustomAtomicComponent extends BaseAtomicComponent {
  // Your implementation
}

// For box components  
export class CustomBoxComponent extends BaseBoxComponent {
  // Your implementation
}
```

#### SelectOptions Implementation Debugging

If filtering isn't working in your custom template:

```typescript
// Verify the selectOptions pattern is implemented
if (this.selectOptions) {
  // Use filtered options
  this.availableOptions = this.selectOptions;
} else {
  // Fall back to all options
  this.availableOptions = this.allOptions;
}
```

Common issues:
- `selectOptions` input not declared with `@Input()`
- Filtering logic not implemented in component
- Template not passing `[selectOptions]` property

## Step 9: Document Your Template

### Add to Frontend Components Documentation

Update `docs/reference-material/frontend-components.md` with:

1. **Description** of your new template
2. **Usage example** in Ampersand script
3. **Frontend example** showing generated HTML
4. **Use cases** and constraints

### Example Documentation Entry

```markdown
## Filtered Dropdown Component

The filtered dropdown component is a specialized version of the object component that allows users to select from a pre-filtered list of options.

### How does it work in an ampersand script:

```ampersand
INTERFACE ProjectForm : "_SESSION"[SESSION]; V[SESSION*Project] cRud BOX<FORM>
  [ "Assign employee" : projectMember cRud BOX<FILTEREDDROPDOWN>
    [ select : eligibleEmployees ]
  ]
```

### How does it work in the front-end:

```html
<app-atomic-object
    [selectOptions]="resource.eligibleEmployees"
    isUni
></app-atomic-object>
```
```

## Step 10: Share and Maintain

### Contribution Guidelines

If contributing back to the framework:

1. **Follow naming conventions** established by existing templates
2. **Maintain backwards compatibility** with existing Ampersand scripts
3. **Include comprehensive tests** covering all constraint combinations
4. **Document thoroughly** with examples and use cases

### Version Compatibility

Ensure your template works with:
- **Current Ampersand compiler version**
- **Target Angular version** of the framework
- **Required PrimeNG components**

## Best Practices Summary

### Do's ✅
- Reuse existing Angular components when possible
- Follow established naming conventions
- Support all standard template variables
- Test with different multiplicity constraints
- Document your template thoroughly

### Don'ts ❌
- Don't break existing template variable patterns
- Don't create unnecessary new Angular components
- Don't ignore multiplicity constraints (UNI/TOT)
- Don't skip testing with real Ampersand scripts
- Don't forget to update documentation

## Conclusion

Creating custom BOX templates allows you to extend the Ampersand framework with specialized interface patterns while maintaining consistency with existing components. By following this guide and using the FILTEREDDROPDOWN as a reference implementation, you can create robust, reusable templates that integrate seamlessly with the framework architecture.

Remember: the key to successful custom templates is balancing customization with consistency, ensuring your templates work harmoniously within the broader Ampersand ecosystem.
