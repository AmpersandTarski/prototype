/// <reference types="cypress" />
// ***********************************************
// This example commands.ts shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

declare global {
  namespace Cypress {
    interface Chainable {
      /**
       * Navigate to the Excel import page
       */
      visitImportPage(): Chainable<void>;

      /**
       * Upload a file to the import component
       * @param fileName - Name of the file to upload
       * @param fileType - MIME type of the file
       */
      uploadFile(fileName: string, fileType?: string): Chainable<void>;

      /**
       * Create and upload a mock Excel file
       * @param fileName - Name for the mock file
       * @param shouldCauseError - Whether the file should trigger an error
       */
      uploadMockExcelFile(
        fileName: string,
        shouldCauseError?: 'server' | 'validation' | false,
      ): Chainable<void>;

      /**
       * Wait for upload to complete or fail
       */
      waitForUploadResult(): Chainable<void>;
    }
  }
}

Cypress.Commands.add('visitImportPage', () => {
  cy.visit('/admin/population/import');
  cy.get('h3').contains('Import population file(s)').should('be.visible');
});

Cypress.Commands.add(
  'uploadFile',
  (
    fileName: string,
    fileType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  ) => {
    // Create a mock file for testing
    const fileContent = 'Mock Excel content for testing';

    // Target the specific file input in the p-fileupload component
    cy.get('p-fileupload input[type="file"]')
      .first()
      .selectFile(
        {
          contents: Cypress.Buffer.from(fileContent),
          fileName: fileName,
          mimeType: fileType,
        },
        { force: true },
      );
  },
);

Cypress.Commands.add(
  'uploadMockExcelFile',
  (fileName: string, shouldCauseError = false) => {
    // Intercept the upload API call
    if (shouldCauseError === 'server') {
      cy.intercept('POST', '/admin/import', {
        statusCode: 500,
        body: {
          error: 'Internal server error occurred during import processing',
        },
      }).as('uploadRequest');
    } else if (shouldCauseError === 'validation') {
      cy.intercept('POST', '/admin/import', {
        statusCode: 400,
        body: { error: 'Invalid data format at line 15, column 3' },
      }).as('uploadRequest');
    } else {
      cy.intercept('POST', '/admin/import', {
        statusCode: 200,
        body: { message: 'Import completed successfully', importedRows: 42 },
      }).as('uploadRequest');
    }

    // Upload the file
    cy.uploadFile(fileName);
  },
);

Cypress.Commands.add('waitForUploadResult', () => {
  cy.wait('@uploadRequest', { timeout: 10000 });
});

// Prevent TypeScript errors
export {};
