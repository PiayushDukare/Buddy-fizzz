<?php require __DIR__ . '/api/config.php'; ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta
      name="description"
      content="Buddy Fizz — bold flavours, real freshness. Sparkling drinks made with real fruit juice, crafted in Goa."
    />
    <title>Buddy Fizz — Open. Fizz. Refresh.</title>
    <?php
      $__url = abs_url('');
      $__ogimg = abs_url('frames/img/ingredient.webp');
      $__desc = 'Bold flavours, real freshness. Sparkling drinks made with real fruit juice, crafted in Goa.';
      $__ld = json_encode([
          '@context' => 'https://schema.org',
          '@type' => 'Organization',
          'name' => get_setting('company_name', 'Buddy Fizz'),
          'url' => $__url,
          'logo' => abs_url('frames/img/ic-green.webp'),
          'image' => $__ogimg,
          'description' => $__desc,
          'email' => get_setting('company_email', ''),
          'telephone' => get_setting('company_phone', ''),
          'address' => [
              '@type' => 'PostalAddress',
              'streetAddress' => get_setting('company_address', ''),
              'addressRegion' => get_setting('company_state', 'Goa'),
              'addressCountry' => 'IN',
          ],
          'areaServed' => 'Goa, India',
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>
    <link rel="canonical" href="<?= htmlspecialchars($__url, ENT_QUOTES) ?>" />
    <meta name="robots" content="index, follow, max-image-preview:large" />
    <meta name="theme-color" content="#143a24" />
    <meta name="keywords" content="Buddy Fizz, sparkling drinks, fizzy soda, real fruit soda, beverage distributor Goa, lemon soda, masala soda, ginger soda" />
    <link rel="icon" type="image/webp" href="frames/img/ic-green.webp" />
    <link rel="apple-touch-icon" href="frames/img/ic-green.webp" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Buddy Fizz" />
    <meta property="og:title" content="Buddy Fizz — Open. Fizz. Refresh." />
    <meta property="og:description" content="<?= htmlspecialchars($__desc, ENT_QUOTES) ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($__url, ENT_QUOTES) ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($__ogimg, ENT_QUOTES) ?>" />
    <meta property="og:locale" content="en_IN" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Buddy Fizz — Open. Fizz. Refresh." />
    <meta name="twitter:description" content="<?= htmlspecialchars($__desc, ENT_QUOTES) ?>" />
    <meta name="twitter:image" content="<?= htmlspecialchars($__ogimg, ENT_QUOTES) ?>" />
    <script type="application/ld+json"><?= $__ld ?></script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link rel="stylesheet" href="css/style.css?v=28" />
  </head>
  <body>
    <div class="topbar">
      <div class="wrap topbar__inner">
        <span><i class="fa-solid fa-leaf"></i> Made with Real Ingredients</span>
        <span class="dot">•</span>
        <span>No Artificial Colours</span>
        <span class="dot">•</span>
        <span>Crafted in Goa</span>
      </div>
    </div>

    <header class="header" id="header">
      <div class="wrap header__inner">
        <a href="#home" class="logo">Buddy<span>Fizz</span></a>
        <nav class="nav" id="nav">
          <a href="#home" class="active">Home</a>
          <a href="#flavours">Flavours</a>
          <a href="#story">Our Story</a>
          <a href="#find">Find Us</a>
          <a href="#contact">Contact</a>
        </nav>
        <div class="header__actions">
          <a href="#" class="btn btn--dark" id="open-apply"
            ><i class="fa-solid fa-handshake"></i> Become a Partner</a
          >
          <button class="burger" id="burger" aria-label="Menu">
            <span></span><span></span><span></span>
          </button>
        </div>
      </div>
    </header>

    <section class="hero" id="home">
      <div class="hero__sticky">
        <canvas class="hero__canvas" id="hero-canvas"></canvas>
        <div class="hero__overlay"></div>

        <div class="wrap hero__content" id="hero-content">
          <h1 class="hero__title">
            <span class="l1">OPEN.</span>
            <span class="l2">FIZZ.</span>
            <span class="l3">REFRESH.</span>
          </h1>
          <p class="hero__sub">
            Bold flavours. Real freshness.<br />Made for every moment.
          </p>
          <div class="hero__cta">
            <a href="#flavours" class="btn btn--green"
              >Explore Flavours <i class="fa-solid fa-chevron-right"></i
            ></a>
            <a href="#story" class="btn btn--ghost">About Buddy Fizz</a>
          </div>
        </div>

        <img
          class="hero__badge"
          src="frames/img/stamp.webp"
          alt="Made with Real Fruit Juice"
        />

        <div class="hero__social">
          <a href="#" aria-label="Instagram"
            ><i class="fa-brands fa-instagram"></i
          ></a>
          <a href="#" aria-label="Facebook"
            ><i class="fa-brands fa-facebook-f"></i
          ></a>
          <a href="#" aria-label="YouTube"
            ><i class="fa-brands fa-youtube"></i
          ></a>
        </div>

        <div class="hero__progress" id="hero-dots">
          <span class="on"></span><span></span><span></span>
        </div>
      </div>
    </section>

    <section class="flavours" id="flavours">
      <div class="wrap">
        <p class="section-kicker reveal">Taste the range</p>
        <h2 class="section-title reveal">Our Refreshing Flavours</h2>
        <div class="flavours__grid">
          <article class="flavour flavour--lemon reveal">
            <img
              class="flavour__art"
              src="frames/img/lemon.webp"
              alt="Buddy Fizz Lemon Lime Soda"
              loading="lazy"
            />
            <div class="flavour__foot">
              <button type="button" class="flavour__btn" data-product="lemon">
                More Info <i class="fa-solid fa-circle-info"></i>
              </button>
            </div>
          </article>
          <article class="flavour flavour--ginger reveal">
            <img
              class="flavour__art"
              src="frames/img/ginger.webp"
              alt="Buddy Fizz Ginger Lemon Soda"
              loading="lazy"
            />
            <div class="flavour__foot">
              <button type="button" class="flavour__btn" data-product="ginger">
                More Info <i class="fa-solid fa-circle-info"></i>
              </button>
            </div>
          </article>
          <article class="flavour flavour--orange reveal">
            <img
              class="flavour__art"
              src="frames/img/orange.webp"
              alt="Buddy Fizz Orange Soda"
              loading="lazy"
            />
            <div class="flavour__foot">
              <button type="button" class="flavour__btn" data-product="orange">
                More Info <i class="fa-solid fa-circle-info"></i>
              </button>
            </div>
          </article>
          <article class="flavour flavour--masala reveal">
            <img
              class="flavour__art"
              src="frames/img/masala.webp"
              alt="Buddy Fizz Masala Soda"
              loading="lazy"
            />
            <div class="flavour__foot">
              <button type="button" class="flavour__btn" data-product="masala">
                More Info <i class="fa-solid fa-circle-info"></i>
              </button>
            </div>
          </article>
          <article class="flavour flavour--grape reveal">
            <img
              class="flavour__art"
              src="frames/img/grape.webp"
              alt="Buddy Fizz Grape Soda"
              loading="lazy"
            />
            <div class="flavour__foot">
              <button type="button" class="flavour__btn" data-product="grape">
                More Info <i class="fa-solid fa-circle-info"></i>
              </button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="features">
      <div class="wrap">
        <p class="section-kicker reveal">The Buddy Fizz Promise</p>
        <h2 class="section-title reveal">Refreshingly Real, Every Sip</h2>
        <div class="features__grid">
          <div class="feature reveal">
            <div class="feature__icon">
              <img src="frames/img/ic-green.webp" alt="" />
            </div>
            <h4>Real Refreshment</h4>
            <p>Perfect fizz for the perfect you.</p>
          </div>
          <div class="feature reveal">
            <div class="feature__icon">
              <img src="frames/img/ic-apple.webp" alt="" />
            </div>
            <h4>Real Ingredients</h4>
            <p>Made with real fruit juice &amp; natural extracts.</p>
          </div>
          <div class="feature reveal">
            <div class="feature__icon">
              <img src="frames/img/ic-dot.webp" alt="" />
            </div>
            <h4>Perfectly Carbonated</h4>
            <p>Just the right fizz. Every single time.</p>
          </div>
          <div class="feature reveal">
            <div class="feature__icon">
              <img src="frames/img/ic-map.webp" alt="" />
            </div>
            <h4>Crafted in Goa</h4>
            <p>Proudly made in India with love.</p>
          </div>
          <div class="feature reveal">
            <div class="feature__icon">
              <img src="frames/img/ic-shild.webp" alt="" />
            </div>
            <h4>Quality Assured</h4>
            <p>Hygienically produced with highest standards.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="split" id="story">
      <div class="wrap story-stack">
        <article class="story-band reveal">
          <div
            class="story-band__img"
            style="background-image: url(&quot;frames/img/beach.webp&quot;)"
          ></div>
          <div class="story-band__content">
            <span class="card-kicker">Who we are</span>
            <h3>Our Story</h3>
            <p class="story-band__lead">
              More than a drink  a little bottle of togetherness.
            </p>
            <p>
              It began on the sunny shores of Goa: a handful of friends, one big
              dream, and the belief that refreshment should taste honest and
              bring people closer. Every Buddy Fizz still carries that spirit 
              real ingredients, real moments, and a little fizz for life's best
              memories. Because everything is better with a buddy.
            </p>
            <a href="#" id="open-story" class="btn btn--green btn--sm"
              >Read Our Story <i class="fa-solid fa-arrow-right"></i
            ></a>
          </div>
        </article>

        <article class="story-band story-band--alt reveal">
          <div
            class="story-band__img"
            style="
              background-image: url(&quot;frames/img/ingredient.webp&quot;);
            "
          ></div>
          <div class="story-band__content">
            <span class="card-kicker">Real &amp; Natural</span>
            <h3>Made with Real Ingredients</h3>
            <ul class="story-band__list">
              <li><i class="fa-solid fa-circle-check"></i> Real Fruit Juice</li>
              <li><i class="fa-solid fa-circle-check"></i> Natural Extracts</li>
              <li>
                <i class="fa-solid fa-circle-check"></i> No Artificial Colours
              </li>
              <li>
                <i class="fa-solid fa-circle-check"></i> Perfect for every
                season
              </li>
            </ul>
            <a href="#flavours" class="btn btn--green btn--sm"
              >Explore Flavours <i class="fa-solid fa-arrow-right"></i
            ></a>
          </div>
        </article>
      </div>
    </section>

    <section class="find" id="find">
      <div class="find__bg"></div>
      <div class="wrap find__grid">
        <div class="find__main reveal">
          <span class="find__kicker"
            ><i class="fa-solid fa-location-dot"></i> Store Locator</span
          >
          <h3>Find Buddy Fizz Near You</h3>
          <p>
            Available in leading stores &amp; outlets across the country. Drop
            in your location and we'll point you to the nearest fizz.
          </p>
          <form class="find__form" id="finder-open-form" onsubmit="return false;">
            <span class="find__input">
              <i class="fa-solid fa-location-crosshairs"></i>
              <input
                type="text"
                id="finder-open-input"
                placeholder="Enter city, district or pincode"
                aria-label="Location"
              />
            </span>
            <button type="submit" class="btn btn--yellow" id="finder-open">
              Find Stores <i class="fa-solid fa-magnifying-glass"></i>
            </button>
          </form>
          <div class="find__tags">
            <div class="find__tag">
              <span class="find__tag-ic"
                ><i class="fa-solid fa-store"></i
              ></span>
              Retail Stores
            </div>
            <div class="find__tag">
              <span class="find__tag-ic"
                ><i class="fa-solid fa-cart-shopping"></i
              ></span>
              Supermarkets
            </div>
            <div class="find__tag">
              <span class="find__tag-ic"
                ><i class="fa-solid fa-truck-fast"></i
              ></span>
              Distributors
            </div>
            <div class="find__tag">
              <span class="find__tag-ic"
                ><i class="fa-solid fa-laptop"></i
              ></span>
              Online Stores
            </div>
          </div>
        </div>
        <div class="find__card reveal">
          <div class="find__card-icon">
            <i class="fa-solid fa-handshake"></i>
          </div>
          <h4>Become a Distributor</h4>
          <p>Partner with us and bring refreshment to your city.</p>
          <a href="#" class="btn btn--yellow btn--sm apply-trigger"
            >Apply Now <i class="fa-solid fa-arrow-right"></i
          ></a>
        </div>
      </div>
    </section>

    <footer class="footer" id="contact">
      <div class="wrap footer__grid">
        <div class="footer__brand">
          <a href="#home" class="logo">Buddy<span>Fizz</span></a>
          <p>
            Open. Fizz. Refresh. Made with real ingredients for real
            refreshment.
          </p>
          <div class="socials">
            <a href="#" aria-label="Instagram"
              ><i class="fa-brands fa-instagram"></i
            ></a>
            <a href="#" aria-label="Facebook"
              ><i class="fa-brands fa-facebook-f"></i
            ></a>
            <a href="#" aria-label="YouTube"
              ><i class="fa-brands fa-youtube"></i
            ></a>
          </div>
        </div>
        <div class="footer__col">
          <h5>Quick Links</h5>
          <a href="#home">Home</a>
          <a href="#flavours">Flavours</a>
          <a href="#story">Our Story</a>
          <a href="#find">Find Us</a>
          <a href="#contact">Contact Us</a>
        </div>
        <div class="footer__col">
          <h5>Our Flavours</h5>
          <a href="#flavours">Lemon</a>
          <a href="#flavours">Ginger Lemon</a>
          <a href="#flavours">Orange</a>
          <a href="#flavours">Masala</a>
          <a href="#flavours">Grape</a>
        </div>
        <div class="footer__col footer__contact">
          <h5>Contact Us</h5>
          <a href="tel:+917774067890"
            ><i class="fa-solid fa-phone"></i> +91 77740 67890</a
          >
          <a href="mailto:buddyfizzcare@gmail.com"
            ><i class="fa-solid fa-envelope"></i> buddyfizzcare@gmail.com</a
          >
          <a href="#"><i class="fa-solid fa-globe"></i> www.buddyfizz.com</a>
          <a href="#"
            ><i class="fa-solid fa-location-dot"></i> Khursa Vaddo, P.O.
            Piedade, Divar, Mallar, North Goa, Goa – 403403.</a
          >
        </div>
        <div class="footer__col footer__news">
          <h5>Stay Connected</h5>
          <p>Follow us for updates, offers &amp; more!</p>
          <form class="footer__form" id="newsletter-form" onsubmit="return false;">
            <input
              type="email"
              id="newsletter-email"
              placeholder="Enter your email"
              aria-label="Email"
              required
            />
            <button type="submit" aria-label="Subscribe">
              <i class="fa-solid fa-paper-plane"></i>
            </button>
          </form>
          <p class="footer__note" id="newsletter-msg"></p>
        </div>
      </div>
      <div class="footer__bottom wrap">
        <span>© 2026 Buddy Fizz. All Rights Reserved.</span>
        <span
          ><a href="#">Privacy Policy</a> ·
          <a href="#">Terms &amp; Conditions</a></span
        >
      </div>
    </footer>

    <div class="modal story-modal" id="story-modal" aria-hidden="true">
      <div class="modal__backdrop" data-close></div>
      <div
        class="modal__card story-modal__card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="story-modal-title"
      >
        <button class="modal__close" data-close aria-label="Close">
          &times;
        </button>
        <div class="story-modal__hero">
          <span class="story-modal__kicker">Est. on the shores of Goa</span>
          <h3 id="story-modal-title">Our Story</h3>
          <p>More than a drink  a little bottle of togetherness.</p>
        </div>
        <div class="story-modal__body">
          <p>
            Every great friendship starts with a simple moment  a shared laugh,
            a warm evening, a cold drink passed from one hand to another.
          </p>
          <p>
            Buddy Fizz began exactly like that. On the sun-soaked shores of Goa,
            a small group of friends found themselves dreaming over long
            conversations and colder drinks. And they noticed something was
            missing: a soda that felt <em>honest</em>  made with real fruit and
            real heart, not just sugar and bubbles.
          </p>
          <p>
            So they decided to make it themselves. Long days in the Goan heat,
            countless trials, and one stubborn belief: that refreshment should
            bring people together, not just quench a thirst. Batch after batch,
            they chased that perfect fizz  bold, bright, and unmistakably real.
          </p>
          <p>
            Today, every bottle still carries that spirit. Crafted in Goa with
            natural ingredients and no artificial colours, Buddy Fizz is made
            for the moments that matter  reunions, road trips, lazy afternoons,
            and every little celebration in between.
          </p>
          <p>
            Because life's best memories are never made alone. They're made with
            a buddy. So open one up, share the fizz, and make the moment a
            little more special.
          </p>
          <span class="story-modal__sign">Open. Fizz. Refresh.</span>
        </div>
      </div>
    </div>

    <div class="modal" id="modal" aria-hidden="true">
      <div class="modal__backdrop" data-close></div>
      <div
        class="modal__card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
      >
        <button class="modal__close" data-close aria-label="Close">
          &times;
        </button>
        <div
          class="modal__media"
          id="modal-media"
          role="img"
          aria-label=""
        ></div>
        <div class="modal__body">
          <span class="modal__tag" id="modal-tag"></span>
          <h3 class="modal__title" id="modal-title"></h3>
          <p class="modal__desc" id="modal-desc"></p>
          <ul class="modal__features" id="modal-features"></ul>
          <div class="modal__meta" id="modal-meta"></div>
          <a href="#find" class="btn btn--green modal__cta" data-close
            >Find a Store <i class="fa-solid fa-location-dot"></i
          ></a>
        </div>
      </div>
    </div>

    <div class="apply-back" id="apply-back">
      <form class="apply-modal" id="apply-form">
        <button type="button" class="apply-close" id="apply-close">
          &times;
        </button>
        <h3>Join the Buddy Fizz Network</h3>
        <p class="apply-lead">
          Apply as a partner or retailer. Our team reviews every application
          before activating your portal account.
        </p>
        <div class="apply-msg" id="apply-msg"></div>
        <div class="apply-toggle">
          <label
            ><input type="radio" name="type" value="retailer" checked />
            <span>Retailer</span></label
          >
          <label
            ><input type="radio" name="type" value="partner" />
            <span>Partner / Distributor</span></label
          >
        </div>
        <div class="apply-grid">
          <div class="af">
            <label>Business Name *</label
            ><input name="business_name" required />
          </div>
          <div class="af">
            <label>Store Type</label
            ><select name="store_type">
              <option value="retail">Retail Store</option>
              <option value="supermarket">Supermarket</option>
              <option value="distributor">Distributor</option>
              <option value="online">Online Store</option>
            </select>
          </div>
          <div class="af">
            <label>Contact Person *</label
            ><input name="contact_name" required />
          </div>
          <div class="af">
            <label>Phone *</label><input name="phone" required />
          </div>
          <div class="af">
            <label>Email *</label><input type="email" name="email" required />
          </div>
          <div class="af">
            <label>Password *</label
            ><input
              type="password"
              name="password"
              minlength="8"
              required
              placeholder="Min 8 characters"
            />
          </div>
          <div class="af af--full">
            <label>Address</label><input name="address" />
          </div>
          <div class="af"><label>Village</label><input name="village" /></div>
          <div class="af"><label>Taluka</label><input name="taluka" /></div>
          <div class="af"><label>District</label><input name="district" /></div>
          <div class="af"><label>State</label><input name="state" /></div>
          <div class="af"><label>Pincode</label><input name="pincode" /></div>
        </div>
        <div class="apply-pin">
          <div class="apply-pin__head">
            <label>Pin your store location on the map</label>
            <div class="apply-pin__btns">
              <button type="button" class="apply-pin__btn" id="pin-search">
                <i class="fa-solid fa-magnifying-glass"></i> Find from address
              </button>
              <button type="button" class="apply-pin__btn" id="pin-locate">
                <i class="fa-solid fa-location-crosshairs"></i> Use my location
              </button>
            </div>
          </div>
          <div class="apply-pin__map" id="pin-map"></div>
          <p class="apply-pin__hint" id="pin-hint">
            Tap the map or drag the pin to set your exact location.
          </p>
          <input type="hidden" name="lat" id="pin-lat" />
          <input type="hidden" name="lng" id="pin-lng" />
        </div>
        <div class="af-hp" aria-hidden="true">
          <label>Company URL</label>
          <input name="company_url" tabindex="-1" autocomplete="off" />
        </div>
        <div class="af af--full apply-captcha">
          <label id="captcha-q">
            <i class="fa-solid fa-shield-halved"></i> Verification…
          </label>
          <input
            name="captcha"
            id="captcha-a"
            inputmode="numeric"
            autocomplete="off"
            required
            placeholder="Type the answer"
          />
        </div>
        <button type="submit" class="apply-submit" id="apply-submit">
          Submit Application
        </button>
      </form>
      <div class="apply-done" id="apply-done" role="alert">
        <button type="button" class="apply-close" id="apply-done-close">
          &times;
        </button>
        <div class="apply-done__badge">
          <svg viewBox="0 0 52 52" aria-hidden="true">
            <circle class="apply-done__circle" cx="26" cy="26" r="24" />
            <path class="apply-done__check" d="M14 27l8 8 16-16" />
          </svg>
        </div>
        <h3>Application received! 🎉</h3>
        <p class="apply-done__msg" id="apply-done-msg">
          Thanks for applying to the Buddy Fizz network.
        </p>
        <ul class="apply-done__steps">
          <li><i class="fa-solid fa-envelope-circle-check"></i> A confirmation email is on its way.</li>
          <li><i class="fa-solid fa-user-check"></i> Our team reviews every application, usually within 1–2 business days.</li>
          <li><i class="fa-solid fa-right-to-bracket"></i> Once approved, you can log in with your email &amp; password.</li>
        </ul>
        <button type="button" class="apply-submit" id="apply-done-btn">
          Done
        </button>
      </div>
    </div>

    <style>
      .apply-back {
        position: fixed;
        inset: 0;
        z-index: 200;
        background: rgba(15, 40, 24, 0.55);
        backdrop-filter: blur(4px);
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding: 4vh 1rem;
        overflow-y: auto;
      }
      .apply-back.open {
        display: flex;
      }
      .apply-modal {
        position: relative;
        background: #fffaf0;
        width: min(100%, 40rem);
        border-radius: 1.2rem;
        padding: 2.2rem;
        box-shadow: 0 2rem 4rem rgba(0, 0, 0, 0.3);
      }
      .apply-modal h3 {
        font-family: "Baloo 2", cursive;
        font-size: 1.7rem;
        color: var(--green-dark);
      }
      .af-hp {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        overflow: hidden;
      }
      .apply-captcha {
        margin-top: 1rem;
      }
      .apply-captcha label {
        color: var(--green-dark);
        font-weight: 600;
      }
      .apply-captcha label i {
        margin-right: 0.35rem;
      }
      .apply-lead {
        color: var(--muted);
        font-size: 0.9rem;
        margin: 0.4rem 0 1.2rem;
      }
      .apply-close {
        position: absolute;
        top: 1rem;
        right: 1.1rem;
        background: none;
        border: none;
        font-size: 1.7rem;
        color: var(--muted);
        cursor: pointer;
        line-height: 1;
      }
      .apply-toggle {
        display: flex;
        gap: 0.7rem;
        margin-bottom: 1.2rem;
      }
      .apply-toggle label {
        flex: 1;
        border: 1.5px solid var(--line);
        border-radius: 0.7rem;
        padding: 0.7rem;
        text-align: center;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
      }
      .apply-toggle input {
        display: none;
      }
      .apply-toggle label:has(input:checked) {
        border-color: var(--green-bright);
        background: #eef6e2;
        color: var(--green-dark);
      }
      .apply-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.9rem;
      }
      .af--full {
        grid-column: 1 / -1;
      }
      .af label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--green-dark);
        margin-bottom: 0.3rem;
      }
      .af input,
      .af select {
        width: 100%;
        border: 1px solid var(--line);
        border-radius: 0.6rem;
        padding: 0.65rem 0.8rem;
        font-family: inherit;
        font-size: 0.9rem;
        outline: none;
        background: #fff;
      }
      .af input:focus,
      .af select:focus {
        border-color: var(--green-bright);
      }
      .apply-pin {
        margin-top: 1.1rem;
      }
      .apply-pin__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.6rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
      }
      .apply-pin__head > label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--green-dark);
      }
      .apply-pin__btns {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }
      .apply-pin__btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #eef6e2;
        color: var(--green-dark);
        border: none;
        border-radius: 999px;
        padding: 0.4rem 0.8rem;
        font-family: inherit;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
      }
      .apply-pin__btn:hover {
        background: #dcefc2;
      }
      .apply-pin__map {
        height: 15rem;
        border-radius: 0.7rem;
        overflow: hidden;
        border: 1px solid var(--line);
        background: #e9efe2;
      }
      .apply-pin__hint {
        font-size: 0.76rem;
        color: var(--muted);
        margin-top: 0.4rem;
      }
      .apply-submit {
        width: 100%;
        margin-top: 1.4rem;
        border: none;
        border-radius: 0.7rem;
        padding: 0.95rem;
        background: var(--green-dark);
        color: #fff;
        font-family: inherit;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
      }
      .apply-submit[disabled] {
        opacity: 0.6;
      }
      .apply-msg {
        font-size: 0.85rem;
        padding: 0.7rem 0.9rem;
        border-radius: 0.6rem;
        margin-bottom: 1rem;
        display: none;
      }
      .apply-msg.err {
        background: #fbe0e0;
        color: #b02a2a;
        display: block;
      }
      .apply-msg.ok {
        background: #dff3df;
        color: #1f7a34;
        display: block;
      }
      .apply-done {
        position: relative;
        display: none;
        background: #fffaf0;
        width: min(100%, 34rem);
        border-radius: 1.2rem;
        padding: 2.6rem 2.2rem;
        box-shadow: 0 2rem 4rem rgba(0, 0, 0, 0.3);
        text-align: center;
      }
      .apply-done.show {
        display: block;
        animation: applyDoneIn 0.35s ease;
      }
      @keyframes applyDoneIn {
        from {
          opacity: 0;
          transform: translateY(1rem) scale(0.98);
        }
        to {
          opacity: 1;
          transform: none;
        }
      }
      .apply-done h3 {
        font-family: "Baloo 2", cursive;
        font-size: 1.7rem;
        color: var(--green-dark);
        margin: 0.4rem 0 0;
      }
      .apply-done__msg {
        color: var(--muted);
        font-size: 0.95rem;
        margin: 0.6rem 0 1.4rem;
      }
      .apply-done__badge {
        width: 6rem;
        height: 6rem;
        margin: 0 auto;
      }
      .apply-done__badge svg {
        width: 100%;
        height: 100%;
      }
      .apply-done__circle {
        fill: none;
        stroke: #7cb342;
        stroke-width: 3;
        stroke-dasharray: 151;
        stroke-dashoffset: 151;
        animation: applyCircle 0.5s ease forwards;
      }
      .apply-done__check {
        fill: none;
        stroke: #2e7d32;
        stroke-width: 4;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 48;
        stroke-dashoffset: 48;
        animation: applyCheck 0.35s 0.45s ease forwards;
      }
      @keyframes applyCircle {
        to {
          stroke-dashoffset: 0;
        }
      }
      @keyframes applyCheck {
        to {
          stroke-dashoffset: 0;
        }
      }
      .apply-done__steps {
        list-style: none;
        padding: 0;
        margin: 0 auto 1rem;
        max-width: 24rem;
        text-align: left;
      }
      .apply-done__steps li {
        display: flex;
        gap: 0.7rem;
        align-items: flex-start;
        font-size: 0.9rem;
        color: #3a4a3d;
        padding: 0.55rem 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
      }
      .apply-done__steps li:last-child {
        border-bottom: none;
      }
      .apply-done__steps li i {
        color: #7cb342;
        margin-top: 0.15rem;
        flex: none;
      }
      .apply-done .apply-submit {
        max-width: 14rem;
        margin: 0.4rem auto 0;
      }
      .btn--login {
        padding: 0.7rem 1.2rem;
      }
      @media (max-width: 560px) {
        .apply-grid {
          grid-template-columns: 1fr;
        }
        .btn--login {
          display: none;
        }
      }
    </style>

    <script>
      (function () {
        const back = document.getElementById("apply-back");
        const form = document.getElementById("apply-form");
        const msg = document.getElementById("apply-msg");
        const submit = document.getElementById("apply-submit");
        const captchaQ = document.getElementById("captcha-q");
        const captchaA = document.getElementById("captcha-a");

        async function loadCaptcha() {
          captchaA.value = "";
          captchaQ.innerHTML =
            '<i class="fa-solid fa-shield-halved"></i> Verification…';
          try {
            const r = await fetch("api/captcha.php", {
              credentials: "same-origin",
            });
            const d = await r.json();
            captchaQ.innerHTML =
              '<i class="fa-solid fa-shield-halved"></i> ' + d.question;
          } catch (e) {
            captchaQ.innerHTML =
              '<i class="fa-solid fa-shield-halved"></i> Verification unavailable';
          }
        }
        const latIn = document.getElementById("pin-lat");
        const lngIn = document.getElementById("pin-lng");
        const hint = document.getElementById("pin-hint");
        let pinMap = null,
          pinMarker = null;

        function setPin(lat, lng, zoom) {
          latIn.value = lat.toFixed(6);
          lngIn.value = lng.toFixed(6);
          if (!pinMarker) {
            pinMarker = L.marker([lat, lng], { draggable: true }).addTo(pinMap);
            pinMarker.on("dragend", () => {
              const p = pinMarker.getLatLng();
              setPin(p.lat, p.lng);
              reverseFill(p.lat, p.lng);
            });
          } else {
            pinMarker.setLatLng([lat, lng]);
          }
          if (zoom) pinMap.setView([lat, lng], zoom);
          hint.textContent = "Location set ✓ — drag the pin to fine-tune.";
        }

        let revSeq = 0;
        async function reverseFill(lat, lng) {
          const mine = ++revSeq;
          hint.textContent = "Fetching address for this spot…";
          try {
            const url =
              "https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=" +
              lat +
              "&lon=" +
              lng;
            const r = await fetch(url, { headers: { Accept: "application/json" } });
            const d = await r.json();
            if (mine !== revSeq) return;
            const a = (d && d.address) || {};
            const setF = (name, val) => {
              if (val && form.elements[name]) form.elements[name].value = val;
            };
            const line = [a.house_number, a.road, a.neighbourhood]
              .filter(Boolean)
              .join(", ");
            setF(
              "address",
              line ||
                (d.display_name
                  ? d.display_name.split(",").slice(0, 2).join(",").trim()
                  : ""),
            );
            setF(
              "village",
              a.village || a.hamlet || a.suburb || a.town || a.city,
            );
            const district = a.state_district || a.district || a.county || "";
            let taluka =
              a.municipality || a.city_district || a.county || a.town || "";
            if (taluka && taluka === district)
              taluka = a.municipality || a.town || a.suburb || "";
            setF("taluka", taluka);
            setF("district", district);
            setF("state", a.state);
            setF("pincode", a.postcode);
            hint.textContent = "Location & address filled ✓ — edit any field if needed.";
          } catch (e) {
            if (mine === revSeq)
              hint.textContent = "Location set ✓ (couldn't auto-fill address — type it in).";
          }
        }

        function initPin() {
          if (pinMap) {
            setTimeout(() => pinMap.invalidateSize(), 200);
            return;
          }
          pinMap = L.map("pin-map").setView([20.5937, 78.9629], 4);
          L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap",
          }).addTo(pinMap);
          pinMap.on("click", (e) => {
            setPin(e.latlng.lat, e.latlng.lng);
            reverseFill(e.latlng.lat, e.latlng.lng);
          });
          setTimeout(() => pinMap.invalidateSize(), 200);
        }

        document.getElementById("pin-locate").addEventListener("click", () => {
          if (!navigator.geolocation) {
            hint.textContent = "Your browser can't share location — tap the map instead.";
            return;
          }
          hint.textContent = "Locating…";
          navigator.geolocation.getCurrentPosition(
            (p) => {
              setPin(p.coords.latitude, p.coords.longitude, 15);
              reverseFill(p.coords.latitude, p.coords.longitude);
            },
            () => (hint.textContent = "Could not get your location — tap the map instead."),
            { enableHighAccuracy: true, timeout: 8000 },
          );
        });

        document
          .getElementById("pin-search")
          .addEventListener("click", async () => {
            const parts = ["village", "taluka", "district", "state", "pincode"]
              .map((n) => form.elements[n].value.trim())
              .filter(Boolean);
            if (!parts.length) {
              hint.textContent = "Fill the location fields first, then search.";
              return;
            }
            hint.textContent = "Searching…";
            try {
              const url =
                "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=in&q=" +
                encodeURIComponent(parts.join(", "));
              const r = await fetch(url, { headers: { Accept: "application/json" } });
              const d = await r.json();
              if (d && d[0]) setPin(parseFloat(d[0].lat), parseFloat(d[0].lon), 14);
              else hint.textContent = "Address not found — tap the map to set it.";
            } catch (e) {
              hint.textContent = "Search failed — tap the map to set it.";
            }
          });

        const donePanel = document.getElementById("apply-done");
        const doneMsg = document.getElementById("apply-done-msg");
        const showDone = (text) => {
          form.style.display = "none";
          if (text) doneMsg.textContent = text;
          donePanel.classList.add("show");
        };
        const showForm = () => {
          donePanel.classList.remove("show");
          form.style.display = "";
        };

        const open = () => {
          showForm();
          back.classList.add("open");
          document.body.style.overflow = "hidden";
          initPin();
          loadCaptcha();
        };
        const close = () => {
          back.classList.remove("open");
          document.body.style.overflow = "";
          setTimeout(showForm, 250);
        };
        document
          .getElementById("apply-done-btn")
          .addEventListener("click", close);
        document
          .getElementById("apply-done-close")
          .addEventListener("click", close);
        document.getElementById("open-apply").addEventListener("click", (e) => {
          e.preventDefault();
          open();
        });
        document.querySelectorAll(".apply-trigger").forEach((b) =>
          b.addEventListener("click", (e) => {
            e.preventDefault();
            open();
          }),
        );
        document.getElementById("apply-close").addEventListener("click", close);
        back.addEventListener("click", (e) => {
          if (e.target === back) close();
        });
        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          msg.className = "apply-msg";
          submit.disabled = true;
          submit.textContent = "Submitting…";
          const payload = Object.fromEntries(new FormData(form).entries());
          try {
            const res = await fetch("api/apply.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || "Something went wrong");
            form.reset();
            if (pinMarker) {
              pinMap.removeLayer(pinMarker);
              pinMarker = null;
            }
            hint.textContent = "Tap the map or drag the pin to set your exact location.";
            msg.className = "apply-msg";
            msg.textContent = "";
            showDone(data.message);
          } catch (err) {
            msg.textContent = err.message;
            msg.className = "apply-msg err";
          }
          loadCaptcha();
          submit.disabled = false;
          submit.textContent = "Submit Application";
        });
      })();
    </script>

    <div class="finder" id="finder">
      <div class="finder__panel">
        <div class="finder__head">
          <div>
            <h3>Find Buddy Fizz Near You</h3>
            <p>Search stores by city, district or pincode.</p>
          </div>
          <button class="finder__close" id="finder-close" aria-label="Close">&times;</button>
        </div>
        <div class="finder__bar">
          <span class="finder__search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="finder-search" placeholder="Search area or pincode…" />
          </span>
          <button class="btn btn--green btn--sm" id="finder-locate"><i class="fa-solid fa-location-crosshairs"></i> Near me</button>
        </div>
        <div class="finder__body">
          <div class="finder__map" id="finder-map"></div>
          <div class="finder__list" id="finder-list"></div>
        </div>
      </div>
    </div>

    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <script
      src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    ></script>
    <script>
      (function () {
        const f = document.getElementById("newsletter-form");
        const msg = document.getElementById("newsletter-msg");
        f.addEventListener("submit", async () => {
          const email = document.getElementById("newsletter-email").value.trim();
          try {
            const r = await fetch("api/subscribe.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({ email }),
            });
            const d = await r.json();
            msg.textContent = r.ok ? d.message : d.error;
            msg.style.color = r.ok ? "#a4d233" : "#ffb3b3";
            if (r.ok) f.reset();
          } catch (e) {
            msg.textContent = "Something went wrong.";
          }
        });
      })();
    </script>
    <script src="assets/finder.js?v=3"></script>
    <script src="js/main.js?v=19"></script>
  </body>
</html>
