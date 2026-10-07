import type { Preview } from '@storybook/angular';
import { applicationConfig } from '@storybook/angular';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';

const preview: Preview = {
  // Services with providedIn: 'root' resolve their dependencies in the application
  // injector. Providers given through a story's moduleMetadata land in a child
  // injector that a root service never sees, so HttpClient and Router are provided
  // here, where AppModule provides them in the application itself.
  decorators: [
    applicationConfig({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    }),
  ],
  parameters: {
    actions: { argTypesRegex: '^on[A-Z].*' },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
  },
};

export default preview;
