// GET /simplyboost/identity: a fresh signature for pages left open longer
// than the 10-minute window. Same origin; the widget calls it with cookies.
import { signedVisitor } from '@/lib/simplyboost';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

export async function GET() {
  const signed = await signedVisitor();
  return Response.json(signed ?? {}, {
    status: signed ? 200 : 401,
    headers: { 'Cache-Control': 'no-store' },
  });
}
