<?xml version="1.0" encoding="utf-8"?>
<!--
  Human-readable styled view for OpenParliamentTV RSS feeds.
  Browsers apply this stylesheet (same-origin) to render a friendly page;
  feed readers ignore it and parse the raw RSS XML directly.
-->
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:dc="http://purl.org/dc/elements/1.1/">
  <xsl:output method="html" encoding="utf-8" indent="yes"
              doctype-system="about:legacy-compat" />

  <xsl:template match="/rss/channel">
    <html lang="{language}">
      <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><xsl:value-of select="title" /></title>
        <style>
          :root { --orange:#f26522; --fg:#1a1a1a; --muted:#667; --line:#e3e3e8; --bg:#fafafb; }
          * { box-sizing:border-box; }
          body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; color:var(--fg); background:var(--bg); line-height:1.5; }
          .wrap { max-width:780px; margin:0 auto; padding:24px 20px 64px; }
          .banner { display:flex; gap:12px; align-items:flex-start; background:#fff7f2; border:1px solid #f6d2bf; border-radius:10px; padding:14px 16px; margin-bottom:28px; font-size:14px; color:#7a3d22; }
          .banner svg { flex:0 0 auto; margin-top:2px; }
          .banner code { background:#fff; border:1px solid var(--line); border-radius:5px; padding:2px 6px; font-size:13px; word-break:break-all; }
          header.feed { display:flex; gap:16px; align-items:center; margin-bottom:8px; }
          header.feed img { width:48px; height:48px; object-fit:contain; }
          h1 { font-size:24px; margin:0; }
          .desc { color:var(--muted); margin:4px 0 0; }
          .meta { color:var(--muted); font-size:13px; margin:18px 0 8px; border-bottom:1px solid var(--line); padding-bottom:8px; }
          ul.items { list-style:none; margin:0; padding:0; }
          li.item { padding:18px 0; border-bottom:1px solid var(--line); }
          li.item h2 { font-size:18px; margin:0 0 6px; }
          li.item h2 a { color:var(--fg); text-decoration:none; }
          li.item h2 a:hover { color:var(--orange); text-decoration:underline; }
          .itemmeta { font-size:13px; color:var(--muted); margin-bottom:6px; }
          .itemmeta .tag { display:inline-block; background:#eee; border-radius:4px; padding:1px 7px; margin-left:6px; color:#444; }
          .snippet { color:#333; font-size:14px; }
          a.brand { color:var(--orange); text-decoration:none; }
          footer { margin-top:36px; font-size:13px; color:var(--muted); }
        </style>
      </head>
      <body>
        <div class="wrap">
          <div class="banner">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#f26522"><path d="M6.18 17.82a2.18 2.18 0 1 0 0 4.36 2.18 2.18 0 0 0 0-4.36zM4 10.1v2.92c2.93 0 5.7 1.14 7.78 3.22A11 11 0 0 1 15 24h2.93A14 14 0 0 0 4 10.1zM4 4v2.93C13.43 6.93 21.07 14.57 21.07 24H24A20 20 0 0 0 4 4z" /></svg>
            <div>
              This is an <strong>RSS feed</strong>. Copy the address from your browser's bar into a feed reader to subscribe and get new items automatically.
            </div>
          </div>

          <header class="feed">
            <xsl:if test="image/url">
              <img src="{image/url}" alt="" />
            </xsl:if>
            <div>
              <h1><xsl:value-of select="title" /></h1>
              <p class="desc"><xsl:value-of select="description" /></p>
            </div>
          </header>

          <div class="meta">
            <xsl:value-of select="count(item)" /> items ·
            <a class="brand" href="{link}">View on the website</a>
          </div>

          <ul class="items">
            <xsl:for-each select="item">
              <li class="item">
                <h2><a href="{link}"><xsl:value-of select="title" /></a></h2>
                <div class="itemmeta">
                  <xsl:value-of select="pubDate" />
                  <xsl:if test="dc:creator">
                    <span class="tag"><xsl:value-of select="dc:creator" /></span>
                  </xsl:if>
                  <xsl:if test="category">
                    <span class="tag"><xsl:value-of select="category" /></span>
                  </xsl:if>
                </div>
                <xsl:if test="description">
                  <div class="snippet"><xsl:value-of select="description" /></div>
                </xsl:if>
              </li>
            </xsl:for-each>
          </ul>

          <footer>
            <a class="brand" href="{link}"><xsl:value-of select="generator" /></a>
          </footer>
        </div>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
