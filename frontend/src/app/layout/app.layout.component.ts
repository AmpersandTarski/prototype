import { Component, OnDestroy, Renderer2, ViewChild } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';
import { filter, Subscription } from 'rxjs';
import { LayoutService } from './service/app.layout.service';
import { MenuService } from './app.menu.service';
import { AppSidebarComponent } from './app.sidebar.component';
import { AppTopBarComponent } from './app.topbar.component';

@Component({
  selector: 'app-layout',
  templateUrl: './app.layout.component.html',
})
export class AppLayoutComponent implements OnDestroy {
  overlayMenuOpenSubscription: Subscription;

  menuOutsideClickListener: any;

  profileMenuOutsideClickListener: any;

  @ViewChild(AppSidebarComponent) appSidebar!: AppSidebarComponent;

  @ViewChild(AppTopBarComponent) appTopbar!: AppTopBarComponent;

  horizontalMenuOutsideClickListener: any;

  constructor(
    public layoutService: LayoutService,
    public menuService: MenuService,
    public renderer: Renderer2,
    public router: Router,
  ) {
    // In horizontal menu mode, close open dropdowns when clicking outside the menu bar
    this.horizontalMenuOutsideClickListener = this.renderer.listen(
      'document',
      'click',
      (event) => {
        if (!this.layoutService.isHorizontal()) {
          return;
        }
        const sidebar = this.appSidebar?.el.nativeElement;
        if (sidebar && !sidebar.contains(event.target)) {
          this.menuService.reset();
        }
      },
    );

    this.overlayMenuOpenSubscription =
      this.layoutService.overlayOpen$.subscribe(() => {
        if (!this.menuOutsideClickListener) {
          this.menuOutsideClickListener = this.renderer.listen(
            'document',
            'click',
            (event) => {
              const isOutsideClicked = !(
                this.appSidebar.el.nativeElement.isSameNode(event.target) ||
                this.appSidebar.el.nativeElement.contains(event.target) ||
                this.appTopbar.menuButton.nativeElement.isSameNode(
                  event.target,
                ) ||
                this.appTopbar.menuButton.nativeElement.contains(event.target)
              );

              if (isOutsideClicked) {
                this.hideMenu();
              }
            },
          );
        }

        if (!this.profileMenuOutsideClickListener) {
          this.profileMenuOutsideClickListener = this.renderer.listen(
            'document',
            'click',
            (event) => {
              const isOutsideClicked = !(
                this.appTopbar.menu.nativeElement.isSameNode(event.target) ||
                this.appTopbar.menu.nativeElement.contains(event.target) ||
                this.appTopbar.topbarMenuButton.nativeElement.isSameNode(
                  event.target,
                ) ||
                this.appTopbar.topbarMenuButton.nativeElement.contains(
                  event.target,
                )
              );

              if (isOutsideClicked) {
                this.hideProfileMenu();
              }
            },
          );
        }

        if (this.layoutService.state.staticMenuMobileActive) {
          this.blockBodyScroll();
        }
      });

    this.router.events
      .pipe(filter((event) => event instanceof NavigationEnd))
      .subscribe(() => {
        this.hideMenu();
        this.hideProfileMenu();
        if (this.layoutService.isHorizontal()) {
          // close open dropdowns after navigating
          this.menuService.reset();
        }
      });
  }

  hideMenu() {
    this.layoutService.state.overlayMenuActive = false;
    this.layoutService.state.staticMenuMobileActive = false;
    this.layoutService.state.menuHoverActive = false;
    if (this.menuOutsideClickListener) {
      this.menuOutsideClickListener();
      this.menuOutsideClickListener = null;
    }
    this.unblockBodyScroll();
  }

  hideProfileMenu() {
    this.layoutService.state.profileSidebarVisible = false;
    if (this.profileMenuOutsideClickListener) {
      this.profileMenuOutsideClickListener();
      this.profileMenuOutsideClickListener = null;
    }
  }

  blockBodyScroll(): void {
    if (document.body.classList) {
      document.body.classList.add('blocked-scroll');
    } else {
      document.body.className += ' blocked-scroll';
    }
  }

  unblockBodyScroll(): void {
    if (document.body.classList) {
      document.body.classList.remove('blocked-scroll');
    } else {
      document.body.className = document.body.className.replace(
        new RegExp(
          '(^|\\b)' + 'blocked-scroll'.split(' ').join('|') + '(\\b|$)',
          'gi',
        ),
        ' ',
      );
    }
  }

  get containerClass() {
    return {
      'layout-theme-light': this.layoutService.config.colorScheme === 'light',
      'layout-theme-dark': this.layoutService.config.colorScheme === 'dark',
      'layout-overlay': this.layoutService.config.menuMode === 'overlay',
      'layout-static': this.layoutService.config.menuMode === 'static',
      'layout-horizontal': this.layoutService.config.menuMode === 'horizontal',
      'layout-static-inactive':
        this.layoutService.state.staticMenuDesktopInactive &&
        this.layoutService.config.menuMode === 'static',
      'layout-overlay-active': this.layoutService.state.overlayMenuActive,
      'layout-mobile-active': this.layoutService.state.staticMenuMobileActive,
      'p-input-filled': this.layoutService.config.inputStyle === 'filled',
      'p-ripple-disabled': !this.layoutService.config.ripple,
    };
  }

  ngOnDestroy() {
    if (this.overlayMenuOpenSubscription) {
      this.overlayMenuOpenSubscription.unsubscribe();
    }

    if (this.menuOutsideClickListener) {
      this.menuOutsideClickListener();
    }

    if (this.horizontalMenuOutsideClickListener) {
      this.horizontalMenuOutsideClickListener();
    }
  }
}
