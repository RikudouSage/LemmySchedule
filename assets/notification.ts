export class Notification {
    private readonly errorUrl: string = '/notice/ajax/error';
    private readonly successUrl: string = '/notice/ajax/success';

    public async error(text: string, translated: boolean = false): Promise<string> {
        const body = new FormData();
        body.set('text', text);
        body.set('translated', String(Number(translated)));

        const response = await fetch(this.errorUrl, {
            method: 'POST',
            body: body,
        });
        if (!response.ok) {
            throw new Error("There was an error while fetching the error text.");
        }

        return await response.text();
    }

    public async success(text: string, translated: boolean = false): Promise<string> {
        const body = new FormData();
        body.set('text', text);
        body.set('translated', String(Number(translated)));

        const response = await fetch(this.successUrl, {
            method: 'POST',
            body: body,
        });
        if (!response.ok) {
            throw new Error("There was an error while fetching the success text.");
        }

        return await response.text();
    }
}
