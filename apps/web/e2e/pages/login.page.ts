import { Page, expect } from '@playwright/test';

export class LoginPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/login');
    await this.page.waitForSelector('[data-testid="login-form-panel"]', { timeout: 15000 });
    await expect(this.page.getByTestId('login-form-panel')).toBeVisible();
  }

  async login(email: string, password: string) {
    await this.page.getByTestId('login-email').fill(email);
    await this.page.getByTestId('login-password').fill(password);
    // Wait for the successful login response AND the token to be stored before navigating.
    // The app dispatches 'ddp-auth-change' and stores token/role in localStorage on success.
    const responsePromise = this.page.waitForResponse((r) => r.url().includes('/api/auth/login') && r.request().method() === 'POST');
    await this.page.getByTestId('login-submit').click();
    const response = await responsePromise;
    expect(response.ok()).toBeTruthy();
    // Wait for localStorage to be populated (the form's onLogin handler does this).
    await this.page.waitForFunction(() => !!localStorage.getItem('ddp_token') && !!localStorage.getItem('ddp_role'), undefined, { timeout: 10000 });
  }
}