export function randomName(): string {
	return crypto.randomUUID().slice(0, 8)
}
