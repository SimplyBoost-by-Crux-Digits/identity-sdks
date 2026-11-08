// App Router root layout: renders the widget for every page, signed for the
// logged-in user. Runs on the Node.js runtime (node:crypto), not Edge.
import { headers } from 'next/headers';
import { widgetSnippet } from '@simplyboost/identity';

import { BOT_ID, signedVisitor } from '@/lib/simplyboost';

export const dynamic = 'force-dynamic'; // sign on every request, never cache a signature

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const nonce = (await headers()).get('x-nonce') ?? undefined; // if your middleware sets a CSP nonce
  const snippet = widgetSnippet({
    botId: BOT_ID,
    visitor: await signedVisitor(),
    refreshUrl: '/simplyboost/identity',
    nonce,
  });
  return (
    <html lang="en">
      <body>
        {children}
        {/* widgetSnippet escapes every value for the <script> element. */}
        <div dangerouslySetInnerHTML={{ __html: snippet }} />
      </body>
    </html>
  );
}
