// Server-only: the identity secret is read here and never reaches the browser.
import 'server-only';
import { visitor, type Visitor } from '@simplyboost/identity';

import { auth } from '@/auth'; // your auth (Auth.js, Clerk, Lucia, ...)

export const BOT_ID = process.env.SIMPLYBOOST_BOT_ID!;
const SECRET = process.env.SIMPLYBOOST_IDENTITY_SECRET!;

export async function signedVisitor(): Promise<Visitor | null> {
  const session = await auth();
  const user = session?.user;
  if (!user?.email) return null;
  return visitor(SECRET, { email: user.email, name: user.name });
}
