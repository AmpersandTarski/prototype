import { HttpErrorResponse } from '@angular/common/http';
import { ErrorHandler, Injectable, NgZone } from '@angular/core';
import { MessageService } from 'primeng/api';

@Injectable()
export class GlobalErrorHandler implements ErrorHandler {
  constructor(private messageService: MessageService, private zone: NgZone) {}

  handleError(error: Error | HttpErrorResponse) {
    let messageSummary = '';
    let messageDetail = '';

    // Assign message content
    if (error instanceof HttpErrorResponse) {
      messageSummary = error.status ? error.status.toString() : 'Error';
      // Prefer the backend's richest field: html carries the stack trace in
      // debug mode (null in production), msg the backend message; Angular's
      // verbose auto-generated HttpErrorResponse.message string comes last.
      messageDetail =
        error.error?.html ?? error.error?.msg ?? error.message ?? '';
    } else {
      messageSummary = error.name;
      messageDetail = error.message;
    }

    // Send message toast
    this.zone.run(() =>
      this.messageService.add({
        severity: 'error',
        summary: messageSummary,
        detail: messageDetail,
        sticky: true,
      }),
    );

    console.log(error);
  }
}
