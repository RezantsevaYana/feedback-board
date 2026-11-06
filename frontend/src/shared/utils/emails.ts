/**
 * Split pasted text (commas, spaces, new lines) into valid emails and the tokens that don't look like one.
 * Both lists come back so the UI can say which pieces were ignored (docs/BUGS.md, «молча пропадает»).
 * Used by the invite modal and by the onboarding's invitations step.
 */
export function parseEmails(input: string): { valid: string[]; invalid: string[] } {
  const seen = new Set<string>()
  const valid: string[] = []
  const invalid: string[] = []
  const emailRe = /^[^@\s]+@[^@\s]+\.[^@\s]+$/
  for (const part of input.split(/[,\s;]+/)) {
    const token = part.trim()
    if (!token) continue
    const email = token.toLowerCase()
    if (seen.has(email)) continue
    seen.add(email)
    if (emailRe.test(email)) valid.push(email)
    else invalid.push(token)
  }
  return { valid, invalid }
}

/** The error line under an emails field for the tokens that aren't emails; empty when all are fine. */
export function invalidEmailsMessage(invalid: string[]): string {
  if (!invalid.length) return ''
  const list = invalid.join(', ')
  return invalid.length === 1
    ? `«${list}» — не похоже на почту, сюда письмо не уйдёт. Исправьте или удалите.`
    : `Не похожи на почту: ${list}. Сюда письма не уйдут — исправьте или удалите.`
}
