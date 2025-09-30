import { defineConfig } from 'cypress';

export default defineConfig({
  e2e: {
    // Don't set a baseUrl by default - let tests specify their own URLs
    // This prevents Cypress from trying to verify a server that might not be running
    baseUrl: null,
    supportFile: 'cypress/support/e2e.ts',
    specPattern: 'cypress/{e2e,stories}/**/*.cy.{js,jsx,ts,tsx}',
    viewportWidth: 1280,
    viewportHeight: 720,
    video: false,
    screenshotOnRunFailure: true,
    setupNodeEvents(on, config) {
      // Set baseUrl based on which tests we're running
      const spec = config.env.CYPRESS_SPEC_PATTERN || config.spec?.toString() || '';
      
      if (spec.includes('stories/')) {
        // Running Storybook tests - no baseUrl needed (tests use full URLs)
        config.baseUrl = null;
      } else if (spec.includes('e2e/')) {
        // Running E2E tests - use localhost for Ampersand app
        config.baseUrl = 'http://localhost';
      }
      
      return config;
    },
  },
});
