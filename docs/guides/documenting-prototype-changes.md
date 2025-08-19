# Documenting Prototype Framework Changes

This guide explains how to properly document changes and additions to the Ampersand Prototype Framework so they appear in the comprehensive documentation at [ampersandtarski.github.io](https://ampersandtarski.github.io/).

## Overview of Ampersand Documentation Architecture

The Ampersand documentation uses a centralized approach where three repositories contribute content to a unified Docusaurus site:
1. __Ampersand__ ([](https://github.com/AmpersandTarski/Ampersand)<https://github.com/AmpersandTarski/Ampersand>)

   - Contains core Ampersand language documentation
   - Provides fundamental theory, syntax, and language reference materials

2. __Prototype Framework__ ([](https://github.com/AmpersandTarski/Prototype)<https://github.com/AmpersandTarski/Prototype>)

   - Contains prototype framework documentation
   - Provides guides like "Creating Custom BOX Templates" and "Documenting Prototype Changes"
   - Also includes reference materials like "BOX Template Architecture"

3. __RAP__ ([](https://github.com/AmpersandTarski/RAP)<https://github.com/AmpersandTarski/RAP>)

   - Contains RAP (Repository for Ampersand Projects) documentation

During the deployment process:

- The documentation from all three repositories' `docs/` folders gets copied into the AmpersandTarski.github.io build process
- They are organized as `tmp/ampersand/`, `tmp/prototype/`, and `tmp/rap/` during the Docker build
- The Docusaurus build system combines them into a unified documentation website
- The GitHub Actions workflow automatically triggers when documentation changes are made to any of these source repositories, to automatically rebuild the site when documentation changes


## Prototype Framework Documentation Structure

Your documentation must follow this structure in the `/docs` folder:

```
docs/
├── sidebar.js              # Navigation configuration (critical!)
├── README.md               # Overview/landing page
├── guides/                 # How-to guides and tutorials
│   ├── creating-custom-box-templates.md
│   └── documenting-prototype-changes.md
└── reference-material/     # Technical reference documentation
    ├── box-template-architecture.md
    ├── frontend-components.md
    └── prototype-framework.md
```

## Step-by-Step Documentation Process

### Step 1: Create Your Documentation

Place your documentation files in the appropriate folder:

- **Guides**: Step-by-step tutorials, how-to instructions → `docs/guides/`
- **Reference**: Technical specifications, API docs, architecture → `docs/reference-material/`

### Step 2: Update sidebar.js (Critical Step)

The `docs/sidebar.js` file controls what appears in the navigation.
This is where you add your new documents, so they appear in the menu of the documentation Docusaurus generates.
Here is an example:

```javascript
module.exports = {
  prototypeGuideSidebar: [
    {
      label: "Creating Custom BOX Templates",
      type: "doc",
      id: "prototype/guides/creating-custom-box-templates",
    },
    {
      label: "Documenting Prototype Changes", 
      type: "doc",
      id: "prototype/guides/documenting-prototype-changes",
    },
    // Add new guides here
  ],
  prototypeReferenceSidebar: [
    {
      label: "Prototype Framework",
      type: "doc", 
      id: "prototype/reference-material/prototype-framework",
    },
    {
      label: "BOX Template Architecture",
      type: "doc",
      id: "prototype/reference-material/box-template-architecture", 
    },
    {
      label: "Frontend components",
      type: "doc",
      id: "prototype/reference-material/frontend-components",
    },
    // Add new reference docs here
  ],
};
```

### Step 3: Document ID Convention

Use this pattern for document IDs in sidebar.js:

- **Guides**: `prototype/guides/[filename-without-extension]`
- **Reference**: `prototype/reference-material/[filename-without-extension]`

The ID must match your file path exactly.

### Step 4: Commit and Push Changes

When you push changes to the main/development branch of the Prototype repository:

1. GitHub Actions detect changes in the `/docs` folder
2. The `triggerDocsUpdate.yml` workflow automatically triggers
3. This calls the `DeployToPages.yml` workflow in `AmpersandTarski.github.io`
4. The main documentation site rebuilds and deploys

### Step 5: Verify Deployment

Check that your documentation appears at https://ampersandtarski.github.io/ within a few minutes of pushing.

## Writing Guidelines

### File Naming

- Use lowercase filenames with hyphens: `creating-custom-box-templates.md`
- Be descriptive but concise
- Match the document ID in sidebar.js

### Content Structure

Follow this template for new documentation:

```markdown
# Document Title

Brief description of what this document covers.

## Prerequisites

What readers need to know before reading this.

## Main Content Sections

Use clear headings and subheadings.

### Code Examples

```html
<!-- Provide clear, working examples -->
<app-atomic-object></app-atomic-object>
```

## Conclusion

Summarize key points and next steps.
```

### Writing Style

Apply the writing style guidelines from `memorybank/schrijfstijl-eisen.md`:

- Use active voice
- Write short, clear sentences
- Avoid unnecessary adjectives and bullet lists
- Be objective and factual

## Common Issues and Solutions

### Documentation Not Appearing

**Problem**: Your documentation doesn't show up on the main site.

**Solution**: Check that you've added the document to `sidebar.js` with the correct ID pattern.

### Broken Links

**Problem**: Internal links don't work properly.

**Solution**: Use relative paths and check that target files exist in the expected locations.

### Sidebar Configuration Errors

**Problem**: Navigation menu is broken.

**Solution**: Validate your `sidebar.js` syntax. Each entry needs `label`, `type`, and `id` properties.

## Integration with Docusaurus

The main documentation site uses these key Docusaurus features:

- **Sidebar Generation**: From the `sidebar.js` files in each repository
- **Content Aggregation**: Pulls `/docs` folders from multiple repositories
- **Search**: Powered by Algolia across all documentation
- **Theme**: Consistent styling across all content

## Workflow Summary

1. **Create** documentation in appropriate `/docs` subfolder
2. **Update** `docs/sidebar.js` to include new documents
3. **Push** changes to main/development branch
4. **Verify** deployment at ampersandtarski.github.io
5. **Test** navigation and links work correctly

## Next Steps

When you add new documentation:

- Consider whether it belongs in guides or reference material
- Update the sidebar configuration immediately
- Test that examples and code snippets work
- Check that the writing follows established style guidelines

The automated deployment system ensures your documentation reaches users quickly once properly configured.
