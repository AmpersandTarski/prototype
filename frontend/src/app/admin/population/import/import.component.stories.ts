import type { Meta, StoryObj } from '@storybook/angular';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { FileUploadModule } from 'primeng/fileupload';
import { ButtonModule } from 'primeng/button';
import { MessagesModule } from 'primeng/messages';
import { ProgressBarModule } from 'primeng/progressbar';
import { MessageService } from 'primeng/api';
import { ImportComponent } from './import.component';
import { PopulationService } from '../population.service';
import { of, Observable } from 'rxjs';
import { delay } from 'rxjs/operators';
import { HttpEventType, HttpErrorResponse } from '@angular/common/http';

// Mock PopulationService for Storybook - Success case with configurable delay
class MockPopulationServiceSuccess {
  constructor(
    private delayMs: number = 3000 + Math.floor(Math.random() * 2000),
  ) {}

  importPopulation(file: File): Observable<any> {
    return new Observable((observer) => {
      const total = 1000000;
      const durationMs = this.delayMs;
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
          observer.next({
            type: HttpEventType.Response,
            status: 200,
            statusText: 'OK',
            body: { message: 'Import successful' },
          });
          observer.complete();
        }
      }, tickMs);

      return () => clearInterval(intervalId);
    });
  }
}

// Mock PopulationService - Server Error (500) after 0.2 seconds
class MockPopulationServiceServerError {
  importPopulation(file: File): Observable<any> {
    return new Observable((observer) => {
      // Simulate very brief progress then error
      setTimeout(() => {
        observer.next({
          type: HttpEventType.UploadProgress,
          loaded: 50000,
          total: 1000000,
        });

        // Error after 0.2 seconds
        setTimeout(() => {
          observer.error(
            new HttpErrorResponse({
              status: 500,
              statusText: 'Internal Server Error',
              error: {
                error:
                  'Database connection failed during import processing. Contact system administrator.',
              },
            }),
          );
        }, 200);
      }, 100);

      return () => {};
    });
  }
}

// Mock PopulationService - Validation Error (400) after 0.2 seconds
class MockPopulationServiceValidationError {
  importPopulation(file: File): Observable<any> {
    return new Observable((observer) => {
      // Simulate very brief progress then error
      setTimeout(() => {
        observer.next({
          type: HttpEventType.UploadProgress,
          loaded: 30000,
          total: 1000000,
        });

        // Error after 0.2 seconds
        setTimeout(() => {
          observer.error(
            new HttpErrorResponse({
              status: 400,
              statusText: 'Bad Request',
              error: {
                error: `Invalid data format in file "${file.name}" at line 15, column 3: Expected numeric value, found text.`,
              },
            }),
          );
        }, 200);
      }, 100);

      return () => {};
    });
  }
}

const meta: Meta<ImportComponent & { delayMs?: number }> = {
  title: 'Admin/Population/Import',
  component: ImportComponent,
  argTypes: {
    delayMs: {
      control: { type: 'number', min: 100, max: 10000, step: 100 },
      defaultValue: 3000,
    },
  },
  parameters: {
    layout: 'centered',
  },
};

export default meta;
type Story = StoryObj<ImportComponent & { delayMs?: number }>;

// Success story - normal usage with configurable delay
export const Success: Story = {
  args: {
    delayMs: 3000,
  },
  decorators: [
    (story, context) => ({
      moduleMetadata: {
        imports: [
          HttpClientTestingModule,
          FileUploadModule,
          ButtonModule,
          MessagesModule,
          ProgressBarModule,
        ],
        providers: [
          {
            provide: PopulationService,
            useFactory: () =>
              new MockPopulationServiceSuccess(context.args['delayMs'] || 3000),
          },
          MessageService,
        ],
      },
    }),
  ],
};

// Quick test story - optimized for automated testing
export const QuickTest: Story = {
  args: {
    delayMs: 500,
  },
  decorators: [
    (story, context) => ({
      moduleMetadata: {
        imports: [
          HttpClientTestingModule,
          FileUploadModule,
          ButtonModule,
          MessagesModule,
          ProgressBarModule,
        ],
        providers: [
          {
            provide: PopulationService,
            useFactory: () =>
              new MockPopulationServiceSuccess(context.args['delayMs'] || 500),
          },
          MessageService,
        ],
      },
    }),
  ],
};

// Story simulating server error (500)
export const ServerError: Story = {
  args: {},
  decorators: [
    (story) => ({
      moduleMetadata: {
        imports: [
          HttpClientTestingModule,
          FileUploadModule,
          ButtonModule,
          MessagesModule,
          ProgressBarModule,
        ],
        providers: [
          {
            provide: PopulationService,
            useClass: MockPopulationServiceServerError,
          },
          MessageService,
        ],
      },
    }),
  ],
};

export const ValidationError: Story = {
  args: {},
  decorators: [
    (story) => ({
      moduleMetadata: {
        imports: [
          HttpClientTestingModule,
          FileUploadModule,
          ButtonModule,
          MessagesModule,
          ProgressBarModule,
        ],
        providers: [
          {
            provide: PopulationService,
            useClass: MockPopulationServiceValidationError,
          },
          MessageService,
        ],
      },
    }),
  ],
};
