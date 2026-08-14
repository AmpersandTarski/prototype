import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AppLayoutComponent } from './layout/app.layout.component';
import { HomeComponent } from './layout/home/home.component';
import { NotFoundComponentComponent } from './layout/not-found-component/not-found-component.component';
import { SignalsComponent } from './layout/signals/signals.component';
import { SessionRouteRedirectGuard } from './shared/guards/session-route-redirect.guard';

const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'signals', component: SignalsComponent },
  { path: '404', component: NotFoundComponentComponent },
  // Catch '{segment}/{id}' URLs where '{segment}' is a SESSION/ONE-scope route
  // (no ':id' variant in the route config). Redirect to the base path instead of 404.
  { path: ':segment/:id', canActivate: [SessionRouteRedirectGuard], component: NotFoundComponentComponent },
  { path: '**', component: NotFoundComponentComponent },
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule],
})
export class AppRoutingModule {}
