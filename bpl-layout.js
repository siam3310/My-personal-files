import { Analytics } from "@vercel/analytics/react";
import Navbar from "./components/Nabar";
import "./globals.css";
import { Inter } from "next/font/google";

const inter = Inter({ subsets: ["latin"] });

export const metadata = {
  title: "BPL 2025 LIVE - bpl25.netlify.app",
  description: "A website where you can watch live BPL 2025 for free - BPL25.NETLIFY.APP",
  verification: { google: "keVTh4CE7NNuJ2uokcjoiAlHfUlakVP7ThDhRKijtCc" },
};

const containerStyle = {
  backgroundImage: "url(assets/bg-image.webp)",
  backgroundRepeat: "no-repeat",
  backgroundPosition: "center center",
  backgroundAttachment: "fixed",
  backgroundSize: "cover",
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <head>
        {/* Google Tag */}
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-C00L020W1Z"></script>
        <script
          dangerouslySetInnerHTML={{
            __html: `
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('config', 'G-C00L020W1Z');
            `,
          }}
        />
      </head>
      <body className={inter.className}>
        <div className="h-screen" style={containerStyle}>
          <Navbar />
          {children}
          <Analytics />
        </div>
      </body>
    </html>
  );
}
