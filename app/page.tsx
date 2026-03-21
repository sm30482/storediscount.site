import Link from "next/link";

export default function HomePage() {
  return (
    <main style={{ maxWidth: 960, margin: "0 auto", padding: "4rem 1.5rem" }}>
      <h1>Store Discount Checkout</h1>
      <p>
        Use <code>/checkout?userID=123&price=14</code> to open a checkout session for one of the supported prices.
      </p>
      <p>
        Example: <Link href="/checkout?userID=123&postID=abc&price=36">Launch sample checkout</Link>
      </p>
    </main>
  );
}
