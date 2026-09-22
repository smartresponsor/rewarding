import { expect, test } from '@playwright/test';

test('standalone Rewarding runtime handles an unknown route through Symfony', async ({ request }) => {
  const response = await request.get('/');

  expect(response.status()).toBe(404);
  expect(response.headers()['content-type']).toContain('text/html');
});
