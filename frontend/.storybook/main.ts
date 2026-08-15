import type { StorybookConfig } from '@storybook/angular';

const config: StorybookConfig = {
  stories: ['../src/**/*.stories.@(js|jsx|mjs|ts|tsx)'],
  addons: [
    {
      name: '@storybook/addon-essentials',
      options: {
        // Disable pipelines that can trigger ShadowCss crash paths in SB+Angular
        actions: true,
        controls: true,
        docs: false,
        // Keep the non-problematic tools
        backgrounds: true,
        viewport: true,
        measure: true,
        outline: true,
        toolbars: true,
      },
    },
    '@storybook/addon-interactions',
  ],
  framework: {
    name: '@storybook/angular',
    options: {
      // Use legacy renderer to avoid ShadowCss issues with some Angular versions
      angularLegacyRendering: true,
    },
  },
  // Ensure Storybook does not try to auto-generate docs pages
  docs: {
    autodocs: false,
  },
};

export default config;
