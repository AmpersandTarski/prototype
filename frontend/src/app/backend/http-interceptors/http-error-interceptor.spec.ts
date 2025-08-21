import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { MessageService } from 'primeng/api';
import { HttpErrorInterceptor } from './http-error-interceptor';

describe('HttpErrorInterceptor', () => {
  beforeEach(() => {
    const routerMock = {
      navigate: jest.fn()
    };

    const messageServiceMock = {
      add: jest.fn()
    };

    TestBed.configureTestingModule({
      providers: [
        HttpErrorInterceptor,
        { provide: Router, useValue: routerMock },
        { provide: MessageService, useValue: messageServiceMock }
      ],
    });
  });

  it('should be created', () => {
    const interceptor: HttpErrorInterceptor =
      TestBed.inject(HttpErrorInterceptor);
    expect(interceptor).toBeTruthy();
  });
});
