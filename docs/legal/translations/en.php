<?php
return [
  'header' => <<<'MD'
**Status: Unreviewed draft. Specialist review and, where appropriate, legal review are pending.**
**Document version: draft-1.0 · Language: English.**
**Operator:** [REQUIRED: full legal name, address and contact details].
**Contract territory:** [REQUIRED: countries actually approved for launch and any necessary country-specific consumer information].

This draft describes only features that have been approved for use. It provides no guarantee of legal effectiveness. Publication as live contractual terms requires documented approval from the appropriate specialists. The operator details, business practices and actual ordering interfaces must be consistent.
MD,
  'documents' => [
    'terms' => [
      'title' => 'General Terms and Conditions',
      'provisions' => <<<'MD'
## 1. Contracting Parties and Scope
These terms govern access to the technical platform and any platform services expressly ordered. The operator facilitates listings between users. As a rule, buyers and sellers enter into contracts for the sale of goods with each other. The platform does not thereby become the owner or seller of the goods or automatically assume responsibility for shipping, handover or storage. Whether a specific feature gives rise to further duties for the operator depends on how it actually works and on mandatory law.

## 2. Accounts, Listings and Contract Formation
Account details must be accurate. Users must keep their login credentials private and report suspicious access without delay. Sellers may list only available, lawful general goods. They must accurately state the condition, price and delivery terms and disclose any commercial status. Contract formation for auctions and fixed-price listings is governed by the separate terms. Category activation and identity verification confer no blanket legal authorization to trade.

## 3. Services, Changes and Termination
The scope, total price and duration of paid services are displayed before ordering and recorded along with the applicable version. Availability, moderation and payment rules must never be changed retroactively without disclosure. For material changes to the contract, the operator must determine whether users need to be informed or must give express consent again. Any declaration required by law must be obtained independently of continued use. Users may request account deactivation and ask for their eligibility for erasure to be assessed under data protection law.

## 4. Responsibility and Liability
The platform must meet its own statutory duties to review, provide information and respond. No impermissible exclusions apply to intentional misconduct, gross negligence, death, personal injury or harm to health, or to mandatory statutory liability, particularly under the German Product Liability Act (Produkthaftungsgesetz), or to guarantees assumed. For a breach of a material contractual obligation through ordinary negligence, liability may be limited to reasonably foreseeable losses typical of the contract only to the extent permitted by law. Indemnification by users is contingent on a violation for which they are responsible and on valid claims. Contributory fault and defenses must still be taken into account.

## 5. Review Before Publication
[REQUIRED: have a specialist review the applicable law, permissible jurisdiction clause for businesses, consumer information, contractual communications and specific description of services.]
MD,
    ],
    'private-use' => [
      'title' => 'Terms of Use for Private Users',
      'provisions' => <<<'MD'
## Account and Permitted Use
Private users act for their own private purposes. Users must not disguise commercial activity as a private sale. Account names must respect third-party rights and must not falsely imply official verification or suggest that verification has taken place when it has not. Required account details must be kept up to date. Unnecessary identity documents must never be submitted through public listings or private messages.

## Listings and Contact
Listings must clearly describe the goods actually available. Prices, condition, collection, shipping costs and apparent defects must be clarified before the contract is concluded. Users may publish images only if they have the necessary rights. The internal chat is for communication about the listing in question. Harassment, mass advertising, phishing, circumvention of restrictions and publication of other people's private content are prohibited.

## Rights and Termination
Free private fixed-price listings are available to the extent specified in the published service description. Paid promotion requires a separate order. The operator gives no assurance of a sale or of any particular reach. Users can manage saved listings and personal features using the tools available to them and can request account deactivation. Outstanding contracts, statutory retention requirements and evidence legitimately needed for disputes may prevent immediate, complete erasure of individual records. Decisions must be clearly explained and open to review.

[REQUIRED: review the minimum age for holding an account, permitted countries, actual limits on free services and contract confirmation.]
MD,
    ],
    'dealer' => [
      'title' => 'Additional Terms for Dealers',
      'provisions' => <<<'MD'
## Business Identity
Commercial sellers must disclose their actual status and provide all legally required business, contact, registration, tax and consumer information. The platform operator verifies dealer details that it is legally required to check, using the designated procedure. Any required authorization from a public authority or specialist must be obtained separately from publishing a profile or uploading a logo.

## Listings and Consumer Obligations
Dealers are responsible under their own contracts for the sale of goods for accurate item descriptions, total prices, delivery costs, availability, statutory rights relating to defects and required information on the right of withdrawal. The platform remains responsible for its own statutory obligations. Cross-border listings require the dealer to clarify delivery, tax treatment, consumer information and whether the product type is permitted in each country targeted.

## Administration and Advertising
Bulk imports must comply with the prescribed formats and product rules. Rows containing errors must not be reported as successfully imported. When staff access is actually offered, each employee must have separate permissions. No provision is made for shared administrator accounts without access controls. Advertising services are subject to a separate booking, review and the advertising terms. The operator guarantees neither sales volumes nor legal authorization to operate as a dealer.

[REQUIRED: review identity verification, dealer contact details, permitted sales territories, contractual communications and any applicable P2B obligations.]
MD,
    ],
    'auction' => [
      'title' => 'Auction Terms for General Goods',
      'provisions' => <<<'MD'
## Scope of Listings and Binding Details
Auctions are permitted only for general goods approved for transactions. Sellers set the starting price, duration and handover terms, along with any available Buy It Now option. Bidding on one's own auction, coordinating sham bidders and artificially inflating prices are prohibited. Any legal product approval required must be obtained independently of the account identity and assigned category.

## Bids and Auction Close
A bidder's maximum bid is kept private. Automatic bidding raises the visible amount only in accordance with the published bid increment rule. When maximum bids are equal, the system gives priority to the first bid accepted. A valid bid in the final two minutes extends the auction until at least two minutes after the bid is accepted. Acceptance time is determined by the server. Late, invalid and seller-placed bids are rejected.

## Contract and Disruptions
[REQUIRED: have a specialist determine exactly when the contract is formed and which declarations form it, based on the actual interface.] The winner and seller receive the information they need to perform the contract through the available contact channels. When data conflict, a pending review does not entitle the operator to name a winner arbitrarily. Material changes after bids have been placed, unauthorized early termination and circumvention of valid contracts are prohibited. Technical disruptions, challenges to a contract, justified withdrawals and demonstrably failed contracts must be handled under a documented procedure. Fees arise only upon the events agreed for that purpose. Outstanding amounts are treated as paid only upon confirmation of payment.
MD,
    ],
    'fees' => [
      'title' => 'Fee and Price Schedule',
      'provisions' => <<<'MD'
## Pricing Basis
The binding total price for a booking is the one displayed before the order that creates an obligation to pay, as recorded with the applicable fee schedule version. Changing the current administrative settings must never increase the price of prior orders retroactively. Tax and net and gross amounts must be shown separately in accordance with the applicable tax classification as verified. [REQUIRED: operator's tax status, VAT treatment and any notices of exemption.]

## Planned Standard Prices
Private fixed-price listings, wanted listings and search alerts are free within the specified limits. Planned prices are EUR 0.50 to list an auction, EUR 1.99 per Bump, EUR 4.99 for three Bumps, EUR 9.90 for a featured category placement for seven days, EUR 19.90 for a featured homepage placement for seven days and EUR 129 for a dealer banner for one month. Features in this list are available only once they are actually offered. If the configured prices differ, they must be displayed clearly before ordering.

## Tiered Auction Commission
The commission is 3 percent on the first EUR 100 of the sale price, 2 percent on the portion above EUR 100 up to EUR 500, 1 percent on the portion above EUR 500 up to EUR 2,000, and 0.5 percent on the amount above that, up to a maximum commission of EUR 29.90. The listing fee is charged separately. A sales commission requires a valid and successful conclusion under the agreed rules. Cancellations, transactions that demonstrably did not go ahead and refunds must be corrected and documented. A fee preview does not constitute an invoice. A customer's notice does not establish receipt of payment.
MD,
    ],
    'premium' => [
      'title' => 'Terms for Bumps and Premium Placements',
      'provisions' => <<<'MD'
## Scope of Service
A Bump moves an existing listing of the user's own up in the sort order by recency. The listing retains its original creation date, bids and auction end time. The same item may be bumped at most once in any 72-hour period. Credit becomes available only after the designated payment check, and the user then activates it deliberately.

## Limited Placements and Durations
Featured category and homepage placements can be booked only to the extent that they are actually available. Before ordering, users see the number of places available, the associated country or category, the total price, the reservation deadline and the duration. A reservation expires at its deadline if unpaid and confers no guaranteed ongoing booking. If payment arrives after the place is no longer available, the operator must avoid overbooking or falsely marking the placement as active and must offer a documented alternative or review whether a refund is due.

## Labeling, Results and Rights
Paid placements are labeled as advertising or sponsored content. Specific numbers of views, contacts or sales are promised only if expressly agreed separately. Statutory consumer rights continue to apply. Immediate commencement of the service requires the express declaration prescribed by law. Placing the order alone does not constitute a blanket waiver of the right of withdrawal. Appropriate adjustments and a review of statutory refund rights must be available for partially unused packages and technical outages.

[REQUIRED: review actual placement quantities, segmentation by country, start rules, compensation for outages and the withdrawal process.]
MD,
    ],
    'advertising' => [
      'title' => 'Advertising Terms for Dealers',
      'provisions' => <<<'MD'
## Campaigns and Review
Advertising may be booked only for lawful services or general goods appropriate for the platform. Advertiser details, destination address, creative and mandatory information must be accurate. Misleading claims of authorization, unlawful products, malware and disguised redirects are prohibited. Approval of an advertisement grants no official or legal authorization for the business model.

## Ad Delivery and Availability
Country, category, duration, placement allocation and total price are set before booking. Serving ads by country provides no assurance that all consumers reached there can be supplied without further obligations. A campaign may be shown as running only once it has been reviewed and paid for and its scheduled period has begun. Limited placements must not be sold twice.

## Statistics and Termination
Click and impression counts are internal metrics calculated under the published counting method. They provide no guarantee of unique individuals and no forecast of sales. Manipulated clicks on one's own ads, automated abuse and unlawful profiling are prohibited. Campaigns may be suspended proportionately where justified by legal or security concerns. The reasons, available complaint process and treatment of any prepaid unused campaign time must be documented.

[REQUIRED: add the provider's identity, monthly contract term, renewal, termination, definitions of statistics and rules on outages and refunds.]
MD,
    ],
    'verification' => [
      'title' => 'Verification and Security Terms',
      'provisions' => <<<'MD'
## Separate Checks
Identity and minimum age are verified separately. Neither check establishes ownership of goods, legal eligibility to acquire them, ability to pay or guaranteed good conduct. Blocked transaction features for regulated goods remain disabled following these checks.

## Documents and Purpose
Only document fields necessary for the stated purpose of the check may be processed. Users should be able to redact unnecessary details. Documents must be kept out of public product images and regular chat. Before submission, users must receive clear information about the responsible party, purpose, legal basis, actual retention period and contact details for questions. Collection remains disabled until these requirements and the technical storage solution have been approved.

## Access, Decision and Erasure
Document images are checked, re-encoded and stored in encrypted form outside the web root. Only reviewers authorized for the individual case receive time-limited access secured by multifactor authentication. Access and decisions are logged. Approval, requests for further information and rejection must be traceable. Files are securely removed after the necessary document retention period determined by specialists. A minimal record of the verification decision may be kept longer only where a separate legal basis applies.

[REQUIRED: have a specialist determine the responsible verification body, legal basis for each form of evidence, document types, specific retention periods and re-verification requirements.]
MD,
    ],
    'privacy' => [
      'title' => 'Privacy Notice',
      'provisions' => <<<'MD'
## Controller and Contact
[REQUIRED: full details of the controller, including address, privacy contact and data protection officer where required.] This notice must reflect the systems actually used and the countries approved for launch. The draft makes no claim that a data protection review has already been completed.

## Purposes and Legal Bases
Account data, listings and necessary contractual communications are processed to provide the service and perform the user agreement insofar as Article 6(1)(b) of the GDPR applies. Processing of financial records or evidence required by law may be based on Article 6(1)(c). Proportionate abuse prevention and security logging require a documented assessment balancing the specific interests under Article 6(1)(f). Where consent under Article 6(1)(a) is relied on, it must be freely given and cannot be inferred from use alone. The specific necessity of processing identity documents and particular case information must be determined separately.

## Data, Recipients and Security
Depending on the feature, processing covers account and contact details, public listings, private messages where authorized, bids, order and payment status, preferences and the minimum necessary security and moderation data. Access to private messages is restricted, and messages are encrypted on the server where that feature has been implemented. The server-side encryption does not provide end-to-end encryption. Payment providers may act as independent controllers for their own services. Hosting providers, local mail transport and any other actual recipients must be specified. [REQUIRED: recipients, processing on behalf of the controller, storage locations, transfers to third countries and safeguards.]

## Retention and Rights
[REQUIRED: clear retention periods for each category of data, including accounts, contracts, messages, security logs and documents.] Users may exercise rights of access, rectification, erasure where provided by law, restriction, portability and, where applicable, objection or withdrawal of consent. Mandatory retention duties continue to apply after a request is made. Users may lodge complaints with a competent data protection supervisory authority. [REQUIRED: competent supervisory authority and clear contact details for exercising rights.]
MD,
    ],
    'imprint' => [
      'title' => 'Legal Notice and Operator Details',
      'provisions' => <<<'MD'
## Service Provider
Full legal name: [REQUIRED: provider name and legal form].
Address for service: [REQUIRED: street, building number, postal code, town or city, and country].
Authorized representative: [REQUIRED: actual representative, where required].
Contact: [REQUIRED: email address and another method suitable for direct communication in practice].

## Registration and Tax Details
Register and registration number: [REQUIRED: actual registration details, where available and required].
VAT identification number or required business identification number: [REQUIRED: accurate details or a specialist's explanation of why they are inapplicable].
Required licensing, professional or supervisory information: [REQUIRED: only details that are actually required and accurate]. Authorities' jurisdiction and approvals must never be fabricated.

## Responsibility for Content and Dispute Resolution
[REQUIRED: person responsible for content where applicable law requires one].
Consumer dispute resolution information must accurately reflect the operator's willingness or legal obligation to participate. Outdated platform links and blanket claims about dispute resolution must be checked before use. The privacy contact and reporting channel must be accessible. The required details must be completed and a specialist review obtained before this legal notice can be published.
MD,
    ],
    'cookies' => [
      'title' => 'Cookie and Storage Information',
      'provisions' => <<<'MD'
## Technically Necessary Features
Sign-in uses a session identifier to associate authorized account access and form submissions with the same session. Security attributes include HttpOnly, SameSite and, for HTTPS, Secure. A CSRF token protects forms that change application state. These features provide no consent to tracking for advertising purposes.

## Display Preferences
The color scheme, animation pause setting and, where applicable, language or market country may be stored locally or in the session to restore the user's chosen display. [REQUIRED: review the actual storage keys, storage types, durations and purpose of each entry]. The interface must honor any change the user makes. Where data protection law requires consent, this option must be left unselected by default.

## Statistics and Third Parties
This draft alone does not activate any advertising networks or external analytics services. Local measurements, abuse counters and any PayPal redirects actually used must be described transparently. Before enabling access to storage for nonessential purposes, the operator must check whether applicable law requires consent. Any tool added later requires a fresh technical and legal assessment. Permission for the existing session does not automatically cover the new tool.

[REQUIRED: inventory all cookies and local storage entries actually set, and specify contact details and how to manage consent.]
MD,
    ],
    'withdrawal' => [
      'title' => 'Notice of the Right of Withdrawal for Relevant Platform Services',
      'provisions' => <<<'MD'
## Scope
This notice must be tailored to the paid services actually offered and to consumers protected by law. [REQUIRED: have a specialist review approved countries, consumer status and the types of contracts covered.] Any differing mandatory consumer rights under the applicable law remain in effect.

## Right of Withdrawal
Where a statutory right of withdrawal exists, consumers may withdraw from the contract within fourteen days without giving a reason. For service contracts, the period generally starts when the contract is concluded, provided the statutory disclosure requirements have been met. To exercise the right, consumers must give an unequivocal notice to [REQUIRED: operator name, address and email address]. Use of the model form is optional. Sending the notice on time meets the deadline subject to the statutory requirements.

## Effects of Withdrawal and Early Start
Payments received must be reimbursed in accordance with statutory deadlines and conditions, generally using the same means of payment unless otherwise validly agreed. If the consumer expressly requested that the service begin during the withdrawal period, proportionate compensation provided for by law can be due only if the requirements for information and declarations have been met. For services, the right of withdrawal can expire entirely only if the relevant statutory conditions are met. Activating a Bump or making a payment alone does not constitute a blanket waiver of rights relating to unused credit or other services in the package.

[REQUIRED: review the specific procedures for ordering, confirmation, service commencement and refunds. Verify the applicable classification before treating services and digital content as equivalent.]
MD,
    ],
    'withdrawal-form' => [
      'title' => 'Model Withdrawal Form',
      'provisions' => <<<'MD'
## Use
This form may be used to withdraw from a consumer contract for a platform service that is subject to a right of withdrawal. Consumers may also submit an unequivocal notice in another form. The separate notice of the right of withdrawal provides the full information. Delete any particulars that are unnecessary.

## Declaration
To [REQUIRED: legal name of the operator, full address and email address]:

I/We hereby give notice that I/we withdraw from my/our contract for the following platform service:

Service and order reference, if applicable: ______________________________
Ordered on: ______________________________
Name of consumer(s): ______________________________
Address of consumer(s): ______________________________
Date: ______________________________
Signature of consumer(s), only for notice given on paper: ______________________________

## Processing
Provide only the information necessary to identify the order and process the request. In general, this requires no copies of identity documents, login credentials or complete confidential payment details. The operator must provide a contact channel that can actually receive the form and must maintain a documented refund process. The operator must accept the form without a processing fee.

[REQUIRED: align contact details and the specific contracts covered with the notice of the right of withdrawal before publication.]
MD,
    ],
    'moderation' => [
      'title' => 'Moderation Guidelines',
      'provisions' => <<<'MD'
## Principles
Moderation follows clear platform rules and applicable law. Unlawful content, fraud, dangerous uploads, harassment, manipulated reviews and regulated goods that have not been approved are prohibited. The platform must continue to fulfill its own statutory obligations and may not shift them entirely to its users.

## Procedure
Reports receive a case number and are reviewed according to urgency and assigned responsibility. Access must be limited to the information needed. A general administrator role alone does not grant full access to private conversations. Additional information submitted voluntarily must be used only for its intended purpose. The platform must give reasons for decisions as required under applicable law and inform affected users of available complaint channels.

## Measures and Limits
Possible measures include a notice, a warning, temporarily holding or hiding specific content, and account restrictions where the platform has the authority to impose them. The scope, duration, severity and recurrence must be considered appropriately. An auction with bids is referred for review if there are inconsistencies. Bids and winners must never be secretly fabricated or removed. A decision in a platform case cannot replace a court ruling on civil claims.

[REQUIRED: have a specialist review actual responsibilities, statutory exceptions, the required content of reasons, DSA applicability and deadlines.]
MD,
    ],
    'complaints' => [
      'title' => 'Reporting and Complaints Procedure',
      'provisions' => <<<'MD'
## Reporting
Users can use the designated reporting channel to report specific listings, users, messages they are authorized to access, reviews and published advertisements. Reports must identify the subject sufficiently and give a clear reason. Any additional information provided voluntarily should be limited to what is necessary. Raising a legitimate concern must not require disclosure of unnecessary identity or payment data.

## Handling and Decision
The platform assigns a case number and documents the review, the person or team responsible for handling it, the decision and the reasons. A report alone does not automatically lead to sanctions. Where required by law, the platform notifies eligible parties of receipt and of the decision. The identity of the person who made the report and any confidential evidence must be protected from blanket disclosure to the other party.

## Complaints
Eligible affected parties may give reasons for requesting review of the decision communicated to them through the designated case channel. Complaints must be processed, and recording a complaint does not automatically lift the measure. A fresh review of the merits or confirmation of the decision is documented. Statutory external complaint, dispute resolution and legal remedies remain available. An internal technical limit on repeated identical submissions must not unlawfully curtail mandatory statutory rights.

[REQUIRED: specify the contact for reporting illegal content, actual deadlines, DSA applicability and out-of-court complaint channels.]
MD,
    ],
    'disputes' => [
      'title' => 'Dispute Terms',
      'provisions' => <<<'MD'
## Contracting Parties
Disputes over the condition of goods, payment, delivery or performance of the sale contract generally concern the buyer and seller as separate contracting parties. The technical platform does not automatically become the seller, shipping provider, insurer or arbitral tribunal. The platform fulfills its own statutory duties and any obligations that it expressly assumes.

## Assistance
The platform may provide access to existing communication channels that the users are authorized to use, along with an internal reporting procedure. Only the necessary data is made available for that purpose. Users must not submit falsified evidence, use other people's accounts or initiate unjustified payment disputes. An internal review does not establish either party's claim by itself.

## Evidence and Remedies
Records of bids, payments and case events must never be secretly altered after the fact. Necessary evidence is retained on a documented legal basis and under a documented retention rule. A manual payment confirmation records the operator's own check and provides no independent judicial or cryptographic confirmation. Statutory claims, court proceedings and out-of-court dispute resolution actually offered remain available. The platform offers no promise of escrow services or a comprehensive purchase price guarantee.

[REQUIRED: review the precise voluntary assistance offered, whether it is free or carries a charge, who is responsible and what dispute resolution information is legally required.]
MD,
    ],
    'payments' => [
      'title' => 'Payment Terms for Platform Services',
      'provisions' => <<<'MD'
## Platform Fees and Purchases of Goods
These terms govern the operator's own fees and do not provide for general collection of purchase payments between users. Available payment methods are shown for the particular order before the contract is concluded. A disabled option on screen does not establish the availability of bank transfer, Bitcoin or escrow.

## Manual and Automated Verification
Manual PayPal payment instructions specify a fixed EUR amount, an accurate service description, the recipient and a unique reference. The operator verifies that payment has actually been received and completed and records a unique transaction ID. A notice from a user, screenshot or browser parameter provides no proof of payment. PayPal account requirements still apply to manual payments. Using the manual process does not justify selecting Friends & Family for commercial services. Automated integration requires the provider configuration actually approved for use and verified server-side events.

## Discrepancies and Fulfillment
Payments with discrepancies in amounts, references or recipients, or with unclear status, must not be confirmed as fully paid. Each transaction may be recorded only once. The confirmed order determines when the service is activated. An expired placement reservation must never result in overbooking after the fact. Errors, cancellations and refunds must be documented through corrective events. Historical invoices must never be altered without disclosure.

[REQUIRED: review the suitability of the recipient account, payment deadlines, default, actual provider terms and contract confirmation.]
MD,
    ],
    'refunds' => [
      'title' => 'Cancellation and Refund Terms',
      'provisions' => <<<'MD'
## Grounds for Review
Refund requests may arise in particular from withdrawal, booked services that were not provided, duplicate payments, demonstrably justified contract corrections or technical outages. Statutory claims remain in effect. Each claim is assessed based on the order and the extent of services actually delivered, with reasons that can be verified.

## Documentation and Reconciliation
The request, reference, reason, decision, amount and payment method actually used must be documented. A refund recorded as requested is complete only once it has been issued. An automated refund can be treated as complete only after verified confirmation from the provider. A manual refund must be linked to a traceable actual transaction. Documents already issued must never be silently overwritten. Cancellation or correction documents must be issued where necessary.

## Credit and Partial Services
For Bump packages and services provided over a fixed term, used and unused portions must be assessed separately. Immediate performance of a single Bump provides no basis for a blanket exclusion of rights relating to the rest of the package. If a premium placement is lost because payment arrived late, the operator must assess genuine remedies. A placement merely marked as active does not constitute performance. Refund deadlines and permissible means of payment follow statutory requirements and valid contractual terms.

[REQUIRED: have a specialist review the actual processing channels, statutory deadlines, correction documents and provider procedures.]
MD,
    ],
    'allowed-products' => [
      'title' => 'Rules for Permitted and Prohibited Products',
      'provisions' => <<<'MD'
## Active Marketplace
The active marketplace is intended for lawful, general, unregulated goods, such as outdoor, camping, clothing, sports, optics, household, tool and media products. The product category does not replace a product review or remove any requirements of the country of delivery or the recipient's country. Renaming previously listed specialized goods does not make them general goods.

## Blocked and Unclassified Products
Unlawful products, counterfeits, stolen goods, fraudulent listings and unapproved regulated product groups involving weapons, ammunition or other products with legal implications must not be traded through general categories. Entering a custom product description or obtaining verification does not grant product approval. Where the classification is unclear, a specialist review is required. Affected transactions remain blocked until that review is complete.

## Duties and Enforcement
Sellers must have authority to dispose of the goods, provide accurate information where required and comply with requirements applicable to the product, shipping and destination country. The operator must fulfill its own statutory and moderation duties. Text filters serve only as a supplementary tool and cannot fully determine a product's legal classification. Attempts to circumvent the rules may result in proportionate restrictions under the moderation procedure, with reasons provided for the decision.

[REQUIRED: have a specialist establish a binding list of product groups, approved countries, who is responsible for review and the statutory restrictions for each type of product.]
MD,
    ],
    'account-suspension' => [
      'title' => 'Account Restriction and Suspension Rules',
      'provisions' => <<<'MD'
## Grounds and Proportionality
Accounts may be temporarily restricted in response to substantiated legal, fraud or security concerns to the extent legally permitted and necessary to prevent harm. The measure must take into account the type, severity, duration, recurrence and effects of the issue. Suspensions require a substantiated reason and may not be arbitrary.

## Effects and Pending Matters
Account deactivation revokes active sessions and prevents newly initiated actions that are no longer permitted. Existing bids, contracts, payments and complaints remain on record. Auctions with bids must never be manipulated by silently deleting a participant. Technical review states and separate handling preserve necessary evidence.

## Reasons and Review
Where legally required, users are informed of the reasons, scope, duration and available means of review. The applicability of statutory exceptions, such as those protecting investigations or third parties, must be assessed for each case. Users must be able to raise a legitimate complaint. External rights remain available. Voluntary account deletion is a separate process from sanctions and requires its own retention decision under data protection law.

[REQUIRED: review deadlines, reconsideration, communications after deactivation, DSA and P2B applicability and contractual termination rules.]
MD,
    ],
  ],
];
