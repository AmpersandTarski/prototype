import { HttpErrorResponse } from '@angular/common/http';
import { Notifications } from './notifications.interface';

type ErrorCode = number;

export type HttpBackendErrorResponse = Omit<HttpErrorResponse, 'error'> & {
  error: BackendErrorResponse;
};

export type HttpBackendErrorResponseText = Omit<HttpErrorResponse, 'error'> & {
  error: Stringified<BackendErrorResponse>;
};

export type BackendErrorResponse = {
  error: ErrorCode;
  msg: string;
  html?: string;
  notifications: Notifications;
};
