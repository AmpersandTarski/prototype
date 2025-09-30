describe('Population Import - Real App Integration Tests', () => {
  // CRITICAL: Environment verification - Clean failing tests if ANY step fails
  before(() => {
    cy.log('🔍 Verifying Ampersand Prototype environment...');

    // Step 1: App accessibility
    cy.request({
      url: 'http://localhost/',
      failOnStatusCode: true,
      timeout: 5000,
    });
    cy.log('✅ App is accessible');

    // Step 2: Verify Ampersand app
    cy.visit('http://localhost/', { failOnStatusCode: true });
    cy.get('body', { timeout: 5000 }).should('be.visible');
    cy.title({ timeout: 5000 }).should('not.be.empty');
    cy.title({ timeout: 5000 }).should('eq', 'Ampersand Prototype');
    cy.get('app-root', { timeout: 5000 }).should('be.visible');
    cy.log('✅ Ampersand app verified');

    // Step 3: Verify import functionality is available
    cy.log('🔍 Checking import functionality...');
    cy.visit('http://localhost/admin/population/import', {
      failOnStatusCode: true,
    });
    cy.get('app-population-import', { timeout: 10000 }).should('be.visible');
    cy.contains('h3', 'Import population file(s)', { timeout: 10000 }).should(
      'be.visible',
    );
    cy.get('p-fileupload', { timeout: 10000 }).should('be.visible');
    cy.get('input[type="file"]', { timeout: 5000 }).should('exist');
    cy.log('✅ Import functionality verified');

    cy.log('🎉 Environment verification complete - all tests can run!');
  });

  // NOTE: All setup is handled in the before() hook above
  // If setup fails, NO tests will run (exactly what we want!)

  describe('Navigation Away During Imports - Toaster Tests', () => {
    beforeEach(() => {
      // Set up interceptor to delay import API calls for testing
      cy.intercept('POST', '**/admin/import', (req) => {
        // Add a 2000ms delay to ensure we have time to trigger cancellation
        return new Promise((resolve) => {
          setTimeout(() => {
            resolve(req.continue());
          }, 3500);
        });
      }).as('importFile');

      cy.visit('http://localhost/admin/population/import');
      cy.get('app-population-import').should('be.visible');
    });

    it('should not show toaster when navigating away without imports', () => {
      cy.log(
        '📋 Testing navigation away with no imports - no toaster expected',
      );

      // Navigate away immediately without importing anything
      cy.visit('http://localhost/');

      // Verify p-toast element exists with expected structure (should be immediate)
      cy.get('p-toast').should('exist');
      cy.get('p-toast').should('have.class', 'p-element');

      // Verify the inner div structure exists
      cy.get('p-toast .p-toast.p-component').should('exist');
      cy.get('p-toast .p-toast.p-component').should(
        'have.class',
        'p-toast-top-right',
      );

      // Most importantly: verify no toast detail elements exist (no actual messages)
      cy.get('.p-toast-detail').should('not.exist');

      cy.log(
        '✅ Toast structure verified - empty toast container with no message details',
      );
    });

    it('should show toaster for 1 pending file when navigating via header click', () => {
      // Create a test file using the fixture approach
      cy.writeFile(
        'cypress/fixtures/test-import.xlsx',
        'Name\nJohn Doe\nJane Smith',
      );

      // Upload the file - this will start automatically and be delayed by our interceptor
      cy.get('p-fileupload input[type="file"]')
        .first()
        .selectFile('cypress/fixtures/test-import.xlsx', { force: true });

      // Wait for file to appear in the import list (this means upload started)
      cy.contains('test-import.xlsx', { timeout: 5000 }).should('be.visible');
      cy.get('.file-list', { timeout: 3000 }).should('be.visible');

      // Navigate away by clicking on "Prototype" in the header (real Angular routing)
      cy.get('app-topbar .layout-topbar-logo span')
        .contains('Prototype')
        .click();

      // Check for toaster with specific message for 1 file
      cy.get('.p-toast-detail', { timeout: 5000 }).should(
        'contain.text',
        'Import of test-import.xlsx has been cancelled.',
      );

      cy.log(
        '✅ Single file cancellation toaster verified with manual ngOnDestroy',
      );
    });

    it('should show toaster for 3 pending files when navigating via header click', () => {
      cy.log(
        '📋📋 Testing header navigation with 3 pending imports using interceptor delay',
      );

      // Create test files
      cy.writeFile('cypress/fixtures/file1.xlsx', 'Name\nFile 1 Data');
      cy.writeFile('cypress/fixtures/file2.xlsx', 'Name\nFile 2 Data');
      cy.writeFile('cypress/fixtures/file3.xlsx', 'Name\nFile 3 Data');

      // Upload multiple files
      cy.get('p-fileupload input[type="file"]')
        .first()
        .selectFile(
          [
            'cypress/fixtures/file1.xlsx',
            'cypress/fixtures/file2.xlsx',
            'cypress/fixtures/file3.xlsx',
          ],
          { force: true },
        );

      // Verify files appear in the import list
      cy.contains('file1.xlsx').should('be.visible');
      cy.contains('file2.xlsx').should('be.visible');
      cy.contains('file3.xlsx').should('be.visible');

      // Navigate away by clicking on "Prototype" in the header (real Angular routing)
      cy.get('app-topbar .layout-topbar-logo span')
        .contains('Prototype')
        .click();

      // Check for toaster with specific message for 3 files
      cy.get('.p-toast-detail', { timeout: 5000 }).should(
        'contain.text',
        'Import of file1.xlsx, file2.xlsx and file3.xlsx have been cancelled.',
      );

      cy.log(
        '✅ Three file cancellation toaster verified with header navigation',
      );
    });

    it('should show generic toaster for 4+ pending files when navigating via header click', () => {
      cy.log(
        '📋📋📋+ Testing header navigation with 4+ pending imports using interceptor delay',
      );

      // Create test files
      cy.writeFile('cypress/fixtures/file1.xlsx', 'Name\nFile 1 Data');
      cy.writeFile('cypress/fixtures/file2.xlsx', 'Name\nFile 2 Data');
      cy.writeFile('cypress/fixtures/file3.xlsx', 'Name\nFile 3 Data');
      cy.writeFile('cypress/fixtures/file4.xlsx', 'Name\nFile 4 Data');

      // Upload multiple files
      cy.get('p-fileupload input[type="file"]')
        .first()
        .selectFile(
          [
            'cypress/fixtures/file1.xlsx',
            'cypress/fixtures/file2.xlsx',
            'cypress/fixtures/file3.xlsx',
            'cypress/fixtures/file4.xlsx',
          ],
          { force: true },
        );

      // Verify files appear in the import list
      cy.contains('file1.xlsx').should('be.visible');
      cy.contains('file4.xlsx').should('be.visible');

      // Navigate away by clicking on "Prototype" in the header (real Angular routing)
      cy.get('app-topbar .layout-topbar-logo span')
        .contains('Prototype')
        .click();

      // Check for toaster with generic message for >3 files
      cy.get('.p-toast-detail', { timeout: 5000 }).should(
        'contain.text',
        '4 Imports cancelled.',
      );

      cy.log(
        '✅ Multiple file cancellation toaster verified with header navigation',
      );
    });
  });

  describe('Navigation Tests', () => {
    it('should verify direct import URL access works', () => {
      cy.log('🔗 Testing direct URL access to import page');

      cy.visit('http://localhost/admin/population/import');
      cy.get('body').should('be.visible');

      // Verify we reach the import component directly
      cy.get('app-population-import', { timeout: 10000 }).should('be.visible');
      cy.contains('h3', 'Import population file(s)').should('be.visible');

      // Verify file upload functionality is present
      cy.get('p-fileupload').should('be.visible');
      cy.get('input[type="file"]').should('exist');
      cy.contains('Select files').should('be.visible');

      cy.log('✅ Direct URL access verified');
    });

    it('should verify home page navigation works', () => {
      cy.log('🏠 Testing home page navigation');

      cy.visit('http://localhost/');
      cy.get('body').should('be.visible');

      // Look for typical home page elements
      cy.get('app-root').should('be.visible');

      // Verify we can navigate back to admin from home
      cy.get('a[href*="admin"]').first().click({ force: true });
      cy.url().should('include', 'admin');

      cy.log('✅ Home page navigation verified');
    });
  });
});
