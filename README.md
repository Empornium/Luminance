Luminance
=========

Note: **BETA SOFTWARE**. This software is currently in heavy development; do not use it for running a live website unless you are quite sure it's appropriate. This document will be updated to reflect changes in this situation, see the *Roadmap* section for details.

Luminance is a webapplication for hosting authentication-restricted torrent tracker websites.
It builds on the heavily altered version of the Gazelle software that has been in use by the Empornium tracker website since 2011.

This document is current as of 2015-09-11.


Goals
-----

Luminance is an attempt to structurally improve and clean up the Gazelle program code. Current goals:

-   Provide a clear, robust and understandable application model based on object-oriented best programming practices.
-   Use modern PHP techniques such as autoloading, namespaces and Composer-installed third party libraries.
-   Greatly enhance security of authentication and encryption to reduce many types of abuse.
-   Use third party libraries where appropriate rather than (sometimes badly) reinventing the wheel.
-   Pave the way for major improvements to application functionality in the future.
-   Remain compatible with recent versions of the Ocelot C++-based tracker software.

These goals might change at any moment, we will attempt to keep this document up-to-date as priorities change.

If you represent a major torrent tracker website and are interested in Luminance, please contact us as we may be open to combining efforts if the practical situation warrants it.


Features
--------

The modified Gazelle used at Empornium contained a great number of changes and feature additions compared to the original What.CD version.
Most are too specific to mention here. Particularly noteworthy differences:

-   No torrent groups, just single torrents.
-   Doubleseed & per-user freeleech options.
-   Bonus point system with credits rewarded from seeding torrents, spent in bonus shop.
-   Torrent checking/approval by staff.
-   User badges.

Although Luminance builds on this, it doesn't currently offer particularly noteworthy new features. Instead it aims to distinguish itself through a higher general level of code quality, which should result in a more secure and stable application. Eventually we do expect to also change and add functionality to a high degree.

For interested developers, the current changes have been applied to the code in its latest version:

-   Namespace-based core classes, with wrappers around legacy code.
-   Separate PHP code away from webroot.
-   Database access abstracted into PDO-based ORM layer, preventing SQL injection by design.
-   Twig-based HTML templates.
-   Monolog-based logging.
-   Clean application model based on Entities (ORM-based), Repositories, Services and Controllers (the latter being mainly Builtins/Plugins)..
-   Fully revised security model based on strong & peer-reviewed cryptographic primitives.


Roadmap
-------

0.5 - First version suitable for peer review and general publication.
0.6-0.9 - Intermediate milestones TBD.
1.0 - Stable version that can be painlessly deployed.


Development
-----------

Right now, Luminance is being exclusively developed by a closed group at Empornium. Current members of the site can apply for a developer position by contacting site staff.

We expect to take a more open attitude towards contributions from others once the code has stabilized more.


Contributors & thanks
---------------------

The currently active core team consists of:

-   Starbuck
-   Dax
-   mifune
-   Dez
-   Mobbo

We would like to thank the following people for their contributions and/or help:

-   UncleMeat
-   Lanz
-   The staff and beta testers at Empornium
-   The original Gazelle developers.
