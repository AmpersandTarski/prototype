import { ImportComponent } from '../../src/app/admin/population/import/import.component';
import { PopulationService } from '../../src/app/admin/population/population.service';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { FileUploadModule } from 'primeng/fileupload';
import { ButtonModule } from 'primeng/button';
import { MessagesModule } from 'primeng/messages';
import { ProgressBarModule } from 'primeng/progressbar';
import { Observable } from 'rxjs';
import { HttpEventType, HttpErrorResponse } from '@angular/common/http';

// Mock PopulationService for testing
class MockPopulationService {
  importPopulation(file: File): Observable<any> {
    // Simulate upload progress over 3-5 seconds, then emit success or error
    return new Observable((observer) => {
      const total = 1000000; // arbitrary total bytes for progress
      const durationMs = 3000 + Math.floor(Math.random() * 2000); // 3-5s
      const tickMs = 100;
      const steps = Math.max(1, Math.floor(durationMs / tickMs));
      const increment = Math.ceil(total / steps);

      let loaded = 0;
      const intervalId = setInterval(() => {
        loaded = Math.min(loaded + increment, total);
        observer.next({
          type: HttpEventType.UploadProgress,
          loaded,
          total,
        });

        if (loaded >= total) {
          clearInterval(intervalId);

          // After "upload", decide outcome based on filename
          if (file.name.includes('error500')) {
            observer.error(
              new HttpErrorResponse({
                status: 500,
                statusText: 'Internal Server Error',
                error: { error: 'Server error during import' },
              }),
            );
          } else if (file.name.includes('error400')) {
            observer.error(
              new HttpErrorResponse({
                status: 400,
                statusText: 'Bad Request',
                error: { error: 'Invalid file format at line 15' },
              }),
            );
          } else {
            observer.next({
              type: HttpEventType.Response,
              status: 200,
              statusText: 'OK',
              body: { message: 'Import successful' },
            });
            observer.complete();
          }
        }
      }, tickMs);

      return () => clearInterval(intervalId);
    });
  }
}

describe('ImportComponent', () => {
  beforeEach(() => {
    // Mount the component with necessary dependencies
    cy.mount(ImportComponent, {
      imports: [
        HttpClientTestingModule,
        FileUploadModule,
        ButtonModule,
        MessagesModule,
        ProgressBarModule,
      ],
      providers: [
        { provide: PopulationService, useClass: MockPopulationService },
      ],
    });
  });

  describe('File Upload Tests', () => {
    it('should add a single file and show it in the list', () => {
      // Create a mock file
      const fileName = 'test-file.json';
      const fileContent = JSON.stringify({ test: 'data' });

      // Create file input and trigger file selection
      cy.get('input[type="file"]').should('exist');

      // Create a File object and trigger the select event programmatically
      cy.window().then((win) => {
        const file = new win.File([fileContent], fileName, {
          type: 'application/json',
        });

        // Get the component instance and trigger file selection
        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8]; // Angular component instance

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Verify file appears in the list
      cy.get('.file-list').should('be.visible');
      cy.get('.file-item').should('have.length', 1);
      cy.contains(fileName).should('be.visible');
      cy.contains('uploading').should('be.visible');
    });

    it('should add 2 files and show them in the list', () => {
      const files = [
        {
          name: 'test-file-1.json',
          content: JSON.stringify({ test: 'data1' }),
        },
        {
          name: 'test-file-2.json',
          content: JSON.stringify({ test: 'data2' }),
        },
      ];

      cy.window().then((win) => {
        const fileObjects = files.map(
          (f) =>
            new win.File([f.content], f.name, { type: 'application/json' }),
        );

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: fileObjects });
          }
        });
      });

      // Verify both files appear in the list
      cy.get('.file-list').should('be.visible');
      cy.get('.file-item').should('have.length', 2);
      files.forEach((file) => {
        cy.contains(file.name).should('be.visible');
      });
    });

    it('should add 3 files and show them in the list', () => {
      const files = [
        {
          name: 'test-file-1.json',
          content: JSON.stringify({ test: 'data1' }),
        },
        {
          name: 'test-file-2.json',
          content: JSON.stringify({ test: 'data2' }),
        },
        {
          name: 'test-file-3.json',
          content: JSON.stringify({ test: 'data3' }),
        },
      ];

      cy.window().then((win) => {
        const fileObjects = files.map(
          (f) =>
            new win.File([f.content], f.name, { type: 'application/json' }),
        );

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: fileObjects });
          }
        });
      });

      // Verify all three files appear in the list
      cy.get('.file-list').should('be.visible');
      cy.get('.file-item').should('have.length', 3);
      files.forEach((file) => {
        cy.contains(file.name).should('be.visible');
      });
    });
  });

  describe('Cancel Upload Tests', () => {
    it('should cancel a single upload and show cancel button', () => {
      const fileName = 'test-file.json';
      const fileContent = JSON.stringify({ test: 'data' });

      cy.window().then((win) => {
        const file = new win.File([fileContent], fileName, {
          type: 'application/json',
        });

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Wait for upload to start and show cancel button
      cy.get('.file-item').should('have.length', 1);
      cy.get('button[aria-label="Cancel upload"]').should('be.visible');

      // Click cancel button
      cy.get('button[aria-label="Cancel upload"]').click();

      // Verify file status changed to canceled
      cy.get('.filename.canceled').should('be.visible');
      cy.get('button[aria-label="Retry upload"]').should('be.visible');
      cy.get('button[aria-label="Clear item"]').should('be.visible');
    });

    it('should clean/remove a canceled upload from the list', () => {
      const fileName = 'test-file.json';
      const fileContent = JSON.stringify({ test: 'data' });

      cy.window().then((win) => {
        const file = new win.File([fileContent], fileName, {
          type: 'application/json',
        });

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Cancel the upload
      cy.get('button[aria-label="Cancel upload"]').should('be.visible').click();

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
      const fileName = 'test-file.json';
      const fileContent = JSON.stringify({ test: 'data' });

      cy.window().then((win) => {
        const file = new win.File([fileContent], fileName, {
          type: 'application/json',
        });

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Cancel the upload
      cy.get('button[aria-label="Cancel upload"]').should('be.visible').click();

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
    it('should handle server errors (500) and show retry option', () => {
      const fileName = 'error500-test-file.json'; // This triggers 500 error in mock
      const fileContent = JSON.stringify({ test: 'data' });

      cy.window().then((win) => {
        const file = new win.File([fileContent], fileName, {
          type: 'application/json',
        });

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Wait for error state
      cy.get('.filename.error', { timeout: 15000 }).should('be.visible');
      cy.get('button[aria-label="Retry upload"]').should('be.visible');
    });

    it('should handle validation errors (400) and show retry option', () => {
      const fileName = 'error400-test-file.json'; // This triggers 400 error in mock
      const fileContent = JSON.stringify({ test: 'data' });

      cy.window().then((win) => {
        const file = new win.File([fileContent], fileName, {
          type: 'application/json',
        });

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Wait for error state
      cy.get('.filename.error', { timeout: 15000 }).should('be.visible');
      cy.get('button[aria-label="Retry upload"]').should('be.visible');
    });
  });

  describe('Progress Indication Tests', () => {
    it('should show progress bar during upload', () => {
      const fileName = 'progress-test-file.json';
      const fileContent = JSON.stringify({ test: 'data' });

      cy.window().then((win) => {
        const file = new win.File([fileContent], fileName, {
          type: 'application/json',
        });

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Verify progress bar appears
      cy.get('.progress-row').should('be.visible');
      cy.get('p-progressbar').should('be.visible');

      // Verify progress updates (should see progress > 0)
      cy.get('.progress-row p-progressbar').should(
        'have.attr',
        'ng-reflect-value',
      );
    });
  });

  describe('Multiple File Upload Bug Tests', () => {
    it('should handle additional files while upload is in progress', () => {
      // First batch of files
      const firstBatch = [
        {
          name: 'first-batch-1.json',
          content: JSON.stringify({ test: 'data1' }),
        },
        {
          name: 'first-batch-2.json',
          content: JSON.stringify({ test: 'data2' }),
        },
      ];

      // Add first batch
      cy.window().then((win) => {
        const fileObjects = firstBatch.map(
          (f) =>
            new win.File([f.content], f.name, { type: 'application/json' }),
        );

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: fileObjects });
          }
        });
      });

      // Verify first batch is uploading
      cy.get('.file-item').should('have.length', 2);
      cy.get('.filename.uploading').should('exist');

      // Add additional file while upload is in progress
      const additionalFile = {
        name: 'additional-file.json',
        content: JSON.stringify({ test: 'additional' }),
      };

      cy.window().then((win) => {
        const file = new win.File(
          [additionalFile.content],
          additionalFile.name,
          { type: 'application/json' },
        );

        cy.get('app-population-import').then((componentEl) => {
          const component = componentEl[0] as any;
          const componentInstance = component.__ngContext__?.[8];

          if (componentInstance && componentInstance.onSelect) {
            componentInstance.onSelect({ files: [file] });
          }
        });
      });

      // Verify all files are in the list (should be 3 total now)
      cy.get('.file-item').should('have.length', 3);
      cy.contains(additionalFile.name).should('be.visible');
    });
  });
});
