import "./globals.css";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Store Discount Checkout",
  description: "Whop-powered checkout flow",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
