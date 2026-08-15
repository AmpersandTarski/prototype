// Loads the Monaco editor at runtime from the assets copied by the build
// (angular.json: node_modules/monaco-editor/min/vs -> /assets/monaco/vs).
// Using Monaco's own AMD loader avoids bundler-specific worker wiring and keeps
// the heavy editor out of the main bundle until a code editor is actually shown.

let loadPromise: Promise<any> | null = null;

export function loadMonaco(): Promise<any> {
  const w = window as any;
  if (w.monaco) return Promise.resolve(w.monaco);
  if (loadPromise) return loadPromise;

  loadPromise = new Promise<any>((resolve, reject) => {
    const baseUrl = `${location.origin}/assets/monaco`;

    const configureAndLoad = () => {
      w.require.config({ paths: { vs: 'assets/monaco/vs' } });
      // Web workers are loaded through a tiny data: shim that pulls the real
      // worker from the assets path (the standard AMD-loader pattern).
      w.MonacoEnvironment = {
        getWorkerUrl: () =>
          `data:text/javascript;charset=utf-8,${encodeURIComponent(
            `self.MonacoEnvironment={baseUrl:'${baseUrl}/'};` +
              `importScripts('${baseUrl}/vs/base/worker/workerMain.js');`,
          )}`,
      };
      w.require(['vs/editor/editor.main'], () => resolve(w.monaco));
    };

    if (w.require) {
      configureAndLoad();
      return;
    }
    const script = document.createElement('script');
    script.src = 'assets/monaco/vs/loader.js';
    script.onload = configureAndLoad;
    script.onerror = (e) => reject(e);
    document.body.appendChild(script);
  });

  return loadPromise;
}
