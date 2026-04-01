import { test, expect } from "@playwright/test";

test('Health check', async ({ request }) => {
    const response = await request.get(
        '/ocs/v2.php/apps/onlyoffice/api/v1/healthcheck',
        {
            headers: {
                'OCS-APIRequest': 'true',
                'Accept': 'application/json',
            },
            failOnStatusCode: true,
        }
    );
    const body = await response.json();
    expect(body.ocs.data.alive).toBe(true);
});
