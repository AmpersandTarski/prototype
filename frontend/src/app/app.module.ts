import { HttpClientModule } from '@angular/common/http';
import { NgModule, APP_INITIALIZER } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { AdminModule } from './admin/admin.module';
import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { httpInterceptorProviders } from './backend/http-interceptors';
import { AppLayoutModule } from './layout/app.layout.module';
import { SharedModule } from './shared/shared.module';
import { ToolsModule } from './tools/tools.module';
import { ToastModule } from 'primeng/toast';
import { CoreModule } from './core/core.module';
import { MessageService } from 'primeng/api';
import { ProjectModule } from './generated/project.module';
import { InterfacesJsonService } from './shared/services/interfaces-json.service';

/**
 * Initializer function to load interfaces.json at app startup
 * Will throw error if file is not available
 */
export function initializeInterfaces(interfacesJsonService: InterfacesJsonService): () => Promise<void> {
  return (): Promise<void> => {
    return interfacesJsonService.loadInterfaces();
  };
}

@NgModule({
  declarations: [AppComponent],
  imports: [
    BrowserModule,
    BrowserAnimationsModule,
    HttpClientModule,
    AppLayoutModule,
    SharedModule,
    CoreModule,
    ProjectModule,
    ToolsModule,
    AdminModule,
    AppRoutingModule,
    ToastModule,
  ],
  providers: [
    httpInterceptorProviders,
    MessageService,
    {
      provide: APP_INITIALIZER,
      useFactory: initializeInterfaces,
      deps: [InterfacesJsonService],
      multi: true
    }
  ],
  bootstrap: [AppComponent],
})
export class AppModule {}
