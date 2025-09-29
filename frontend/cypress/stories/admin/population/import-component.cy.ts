describe('Import Component - Storybook Tests', () => {
  const storybookBaseUrl = 'http://localhost:6006';

  beforeEach(() => {
    // Visit Storybook and wait for it to load
    cy.visit(
      `${storybookBaseUrl}/iframe.html?id=admin-population-import--success&viewMode=story`,
    );
    cy.get('app-population-import').should('be.visible');
  });

  describe('Setup Tests', () => {
    it('should show "Import population file(s)" heading', () => {
      cy.contains('h3', 'Import population file(s)').should('be.visible');
    });

    it('should show "Select files" button that is visible and clickable', () => {
      // Verify the "Select files" button is visible and clickable
      cy.get('p-fileupload').contains('Select files').should('be.visible');
      cy.get('p-fileupload input[type="file"]').should('exist');
    });

    it('should show "Cancel all" button that is visible but disabled initially', () => {
      // Wait a moment for component to initialize and change detection to run
      cy.wait(1000);

      // The Cancel all button should be visible but have p-disabled class (no files uploaded)
      cy.get('p-fileupload .p-fileupload-buttonbar p-button button span')
        .contains('Cancel all')
        .parent()
        .parent()
        .should('have.class', 'p-disabled');
    });
  });

  describe('File Upload Tests', () => {
    it('should add a single file and show it in the list', () => {
      // Create test file
      const fileName = 'test-single-file.json';
      const fileContent = '{"test": "data"}';

      // Upload file using the file input
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/json',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          // Set files on the input element
          (input[0] as HTMLInputElement).files = dataTransfer.files;

          // Trigger change event
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify file appears in the list
      cy.get('.file-list', { timeout: 10000 }).should('be.visible');
      cy.get('.file-item').should('have.length', 1);
      cy.contains(fileName).should('be.visible');

      // Verify the file shows as uploading initially
      cy.get('.filename.uploading').should('exist');
      cy.get('button[aria-label="Cancel upload"]').should('be.visible');
    });

    it('should add 2 files and show them in the list', () => {
      const files = [
        { name: 'test-file-1.json', content: '{"test": "data1"}' },
        { name: 'test-file-2.json', content: '{"test": "data2"}' },
      ];

      // Upload both files
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const dataTransfer = new DataTransfer();
          files.forEach((f) => {
            const file = new File([f.content], f.name, {
              type: 'application/json',
            });
            dataTransfer.items.add(file);
          });

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify both files appear in the list
      cy.get('.file-list', { timeout: 10000 }).should('be.visible');
      cy.get('.file-item').should('have.length', 2);
      files.forEach((file) => {
        cy.contains(file.name).should('be.visible');
      });
    });

    it('should add 3 files and show them in the list', () => {
      const files = [
        { name: 'test-file-1.json', content: '{"test": "data1"}' },
        { name: 'test-file-2.json', content: '{"test": "data2"}' },
        { name: 'test-file-3.json', content: '{"test": "data3"}' },
      ];

      // Upload all three files
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const dataTransfer = new DataTransfer();
          files.forEach((f) => {
            const file = new File([f.content], f.name, {
              type: 'application/json',
            });
            dataTransfer.items.add(file);
          });

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify all three files appear in the list
      cy.get('.file-list', { timeout: 10000 }).should('be.visible');
      cy.get('.file-item').should('have.length', 3);
      files.forEach((file) => {
        cy.contains(file.name).should('be.visible');
      });
    });
  });

  describe('Cancel Upload Tests', () => {
    it('should cancel a single upload and show appropriate buttons', () => {
      const fileName = 'test-cancel-file.json';
      const fileContent = '{"test": "cancel"}';

      // Upload file
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/json',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Wait for file to appear and start uploading
      cy.get('.file-item', { timeout: 10000 }).should('have.length', 1);
      cy.get('button[aria-label="Cancel upload"]').should('be.visible');

      // Click cancel button
      cy.get('button[aria-label="Cancel upload"]').click();

      // Verify file status changed to canceled
      cy.get('.filename.canceled').should('be.visible');
      cy.get('button[aria-label="Retry upload"]').should('be.visible');
      cy.get('button[aria-label="Clear item"]').should('be.visible');
    });

    it('should clean/remove a canceled upload from the list', () => {
      const fileName = 'test-clean-file.json';
      const fileContent = '{"test": "clean"}';

      // Upload and cancel file
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/json',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Cancel the upload
      cy.get('button[aria-label="Cancel upload"]', { timeout: 10000 })
        .should('be.visible')
        .click();

      // Verify file is in canceled state
      cy.get('.filename.canceled').should('be.visible');
      cy.get('button[aria-label="Clear item"]').should('be.visible');

      // Click clear button
      cy.get('button[aria-label="Clear item"]').click();

      // Verify file is removed from the list
      cy.get('.file-item').should('have.length', 0);
      cy.get('.file-list').should('not.exist');
    });

    it('should retry a canceled upload', () => {
      const fileName = 'test-retry-file.json';
      const fileContent = '{"test": "retry"}';

      // Upload and cancel file
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/json',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Cancel the upload
      cy.get('button[aria-label="Cancel upload"]', { timeout: 10000 })
        .should('be.visible')
        .click();

      // Verify file is in canceled state
      cy.get('.filename.canceled').should('be.visible');
      cy.get('button[aria-label="Retry upload"]').should('be.visible');

      // Click retry button
      cy.get('button[aria-label="Retry upload"]').click();

      // Verify file status changed back to uploading
      cy.get('.filename.uploading').should('be.visible');
      cy.get('button[aria-label="Cancel upload"]').should('be.visible');
      cy.get('.progress-row').should('be.visible');
    });
  });

  describe('Error Handling Tests', () => {
    it('should handle server errors (500) and show error message with retry option', () => {
      // Navigate to ServerError story - this story automatically generates 500 errors
      cy.visit(
        `${storybookBaseUrl}/iframe.html?id=admin-population-import--server-error&viewMode=story`,
      );
      cy.get('app-population-import').should('be.visible');

      const fileName = '-upload-file.xls'; // Normal filename - story mock will generate error
      const fileContent = 'Mock Excel content for server error test';

      // Upload file - the ServerError story mock will automatically generate a 500 error
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/vnd.ms-excel',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify the file appears first
      cy.get('.file-item', { timeout: 10000 }).should('have.length', 1);
      cy.contains(fileName).should('be.visible');

      // Wait for progress to show briefly, then error state
      cy.get('.progress-row', { timeout: 5000 }).should('be.visible');
      cy.get('.filename.error', { timeout: 15000 }).should('be.visible');

      // Verify error message is displayed
      cy.get('.error-message').should('be.visible');
      cy.get('.error-message').should(
        'contain.text',
        'Database connection failed',
      );

      // Verify retry button is available
      cy.get('button[aria-label="Retry upload"]').should('be.visible');
    });

    it('should handle validation errors (400) and show detailed error message with retry option', () => {
      // Navigate to ValidationError story - this story automatically generates 400 errors
      cy.visit(
        `${storybookBaseUrl}/iframe.html?id=admin-population-import--validation-error&viewMode=story`,
      );
      cy.get('app-population-import').should('be.visible');

      const fileName = 'stamtabellen.xlsx'; // Normal filename - story mock will generate validation error
      const fileContent = 'Mock Excel content for validation error test';

      // Upload file - the ValidationError story mock will automatically generate a 400 error
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify the file appears first
      cy.get('.file-item', { timeout: 10000 }).should('have.length', 1);
      cy.contains(fileName).should('be.visible');

      // Wait for progress to show briefly, then error state
      cy.get('.progress-row', { timeout: 5000 }).should('be.visible');
      cy.get('.filename.error', { timeout: 15000 }).should('be.visible');

      // Verify detailed error message is displayed
      cy.get('.error-message').should('be.visible');
      cy.get('.error-message').should('contain.text', 'Invalid data format');
      cy.get('.error-message').should('contain.text', 'line 15, column 3');
      cy.get('.error-message').should('contain.text', fileName); // Should include filename

      // Verify retry button is available
      cy.get('button[aria-label="Retry upload"]').should('be.visible');
    });

    it('should retry failed upload and show progress again', () => {
      // Navigate to ServerError story for consistent error behavior
      cy.visit(
        `${storybookBaseUrl}/iframe.html?id=admin-population-import--server-error&viewMode=story`,
      );
      cy.get('app-population-import').should('be.visible');

      const fileName = 'retry-test-file.json';
      const fileContent = '{"test": "retry"}';

      // Upload file that will fail
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/json',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Wait for error state
      cy.get('.filename.error', { timeout: 15000 }).should('be.visible');
      cy.get('button[aria-label="Retry upload"]').should('be.visible');

      // Click retry
      cy.get('button[aria-label="Retry upload"]').click();

      // Verify file goes back to uploading state
      cy.get('.filename.uploading').should('be.visible');
      cy.get('.progress-row').should('be.visible');
      cy.get('button[aria-label="Cancel upload"]').should('be.visible');

      // Should fail again (since we're still in ServerError story)
      cy.get('.filename.error', { timeout: 15000 }).should('be.visible');
    });
  });

  describe('Progress Indication Tests', () => {
    it('should show progress bar during upload', () => {
      // Navigate to Success story (progress testing functionality is same)
      cy.visit(
        `${storybookBaseUrl}/iframe.html?id=admin-population-import--success&viewMode=story`,
      );
      cy.get('app-population-import').should('be.visible');

      const fileName = 'progress-test-file.json';
      const fileContent = '{"test": "progress"}';

      // Upload file
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([fileContent], fileName, {
            type: 'application/json',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify progress bar appears
      cy.get('.progress-row', { timeout: 10000 }).should('be.visible');
      cy.get('p-progressbar').should('be.visible');

      // Verify progress updates (should see progress value attribute)
      cy.get('.progress-row p-progressbar').should(
        'have.attr',
        'ng-reflect-value',
      );
    });
  });

  describe('Multiple File Upload Bug Tests', () => {
    it('should handle additional files while upload is in progress', () => {
      // Navigate to Success story (multiple file upload functionality is same)
      cy.visit(
        `${storybookBaseUrl}/iframe.html?id=admin-population-import--success&viewMode=story`,
      );
      cy.get('app-population-import').should('be.visible');

      // First batch of files
      const firstBatch = [
        { name: 'first-batch-1.json', content: '{"test": "data1"}' },
        { name: 'first-batch-2.json', content: '{"test": "data2"}' },
      ];

      // Add first batch
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const dataTransfer = new DataTransfer();
          firstBatch.forEach((f) => {
            const file = new File([f.content], f.name, {
              type: 'application/json',
            });
            dataTransfer.items.add(file);
          });

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify first batch is uploading
      cy.get('.file-item', { timeout: 10000 }).should('have.length', 2);
      cy.get('.filename.uploading').should('exist');

      // Add additional file while upload is in progress (simulate the bug scenario)
      const additionalFile = {
        name: 'additional-file.json',
        content: '{"test": "additional"}',
      };

      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const file = new File([additionalFile.content], additionalFile.name, {
            type: 'application/json',
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify all files are in the list (should be 3 total now)
      cy.get('.file-item').should('have.length', 3);
      cy.contains(additionalFile.name).should('be.visible');
    });
  });

  describe('Cancel All Button State Tests', () => {
    it('should disable "Cancel all" button after all files are successful', () => {
      const files = [
        { name: 'success-file-1.json', content: '{"test": "data1"}' },
        { name: 'success-file-2.json', content: '{"test": "data2"}' },
      ];

      // Upload 2 files
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const dataTransfer = new DataTransfer();
          files.forEach((f) => {
            const file = new File([f.content], f.name, {
              type: 'application/json',
            });
            dataTransfer.items.add(file);
          });

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Verify both files are in the list
      cy.get('.file-item', { timeout: 10000 }).should('have.length', 2);

      // Cancel all button should be enabled initially (while uploading) - no p-disabled class
      cy.get('p-fileupload .p-fileupload-buttonbar p-button button span')
        .contains('Cancel all')
        .parent()
        .parent()
        .should('not.have.class', 'p-disabled');

      // Wait for both files to complete
      cy.get('.filename.done', { timeout: 20000 }).should('have.length', 2);

      // Verify "Cancel all" button now has p-disabled class
      cy.get('p-fileupload .p-fileupload-buttonbar p-button button span')
        .contains('Cancel all')
        .parent()
        .parent()
        .should('have.class', 'p-disabled');
    });

    it('should disable "Cancel all" button after one success and one cancelled', () => {
      const files = [
        { name: 'mixed-file-1.json', content: '{"test": "data1"}' },
        { name: 'mixed-file-2.json', content: '{"test": "data2"}' },
      ];

      // Upload 2 files
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const dataTransfer = new DataTransfer();
          files.forEach((f) => {
            const file = new File([f.content], f.name, {
              type: 'application/json',
            });
            dataTransfer.items.add(file);
          });

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Wait for files to start uploading
      cy.get('.file-item', { timeout: 10000 }).should('have.length', 2);
      cy.get('button[aria-label="Cancel upload"]').should('be.visible');

      // Cancel the first file while it's uploading
      cy.get('button[aria-label="Cancel upload"]').first().click();

      // Wait for one file to complete and one to be cancelled
      cy.get('.filename.done', { timeout: 15000 }).should('have.length', 1);
      cy.get('.filename.canceled').should('have.length', 1);

      // Verify "Cancel all" button now has p-disabled class (all done or canceled)
      cy.get('p-fileupload .p-fileupload-buttonbar p-button button span')
        .contains('Cancel all')
        .parent()
        .parent()
        .should('have.class', 'p-disabled');
    });

    it('should keep "Cancel all" button enabled when there are error files (can be retried)', () => {
      // Navigate to ServerError story to ensure files will error
      cy.visit(
        `${storybookBaseUrl}/iframe.html?id=admin-population-import--server-error&viewMode=story`,
      );
      cy.get('app-population-import').should('be.visible');

      // Upload files - ServerError story will make them all fail
      const files = [
        { name: 'test-file-1.json', content: '{"test": "data1"}' },
        { name: 'test-file-2.json', content: '{"test": "data2"}' },
      ];

      // Upload both files
      cy.get('p-fileupload input[type="file"]')
        .first()
        .then((input) => {
          const dataTransfer = new DataTransfer();
          files.forEach((f) => {
            const file = new File([f.content], f.name, {
              type: 'application/json',
            });
            dataTransfer.items.add(file);
          });

          (input[0] as HTMLInputElement).files = dataTransfer.files;
          cy.wrap(input).trigger('change', { force: true });
        });

      // Wait for both files to error (ServerError story makes all uploads fail)
      cy.get('.filename.error', { timeout: 15000 }).should('have.length', 2);

      // Verify "Cancel all" button remains enabled - no p-disabled class (error files can be retried)
      cy.get('p-fileupload .p-fileupload-buttonbar p-button button span')
        .contains('Cancel all')
        .parent()
        .parent()
        .should('not.have.class', 'p-disabled');
    });
  });
});
