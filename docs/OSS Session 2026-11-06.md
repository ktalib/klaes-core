# OSS Session: Corrections

Cleaned from the speech-to-text of a walkthrough of the One-Stop Shop (OSS) module, then Land file commissioning. Cleaned on 6 Nov 2026; the recording date is not stated.

- **Speakers:** the recording does not name them. "Reviewer" is the person raising the corrections, and "Developer" is the person answering.
- **Words corrected from the raw text:** arrow / Arab = RofO; fire = file; phone / fan number = file number, where the sense requires it; duties / GOT / QT / UT = ToT; USS = OSS; mode / model = Mother; pounds = files.
- **Unclear passages:** marked `[unclear]`, with the words as heard. Nothing has been guessed beyond the corrections above.
- **Removed:** repetition, false starts, and a personal side conversation about a receipt and a warranty.

---

## Corrections raised

The Reviewer numbered items 1 to 5 during the session. The rest were raised without a number.

| # | Area | Correction |
|---|------|------------|
| 1 | Backfill | The phone number and applicant address entered at commissioning are missing later, on Update OP and on the OSS RofO. Users have been editing records by hand to get round this. |
| 2 | TP dropdown | The TP dropdown was not showing before, and the Reviewer does not know why. `[unclear: the recording is not clear that this is item 2]` |
| 3 | Capture OP for Commissioned File | The second button should load only 2026 files. File numbers from other years can still be selected. |
| 4 | Application options menu | Disable "Edit this record". Its residential address field is not linked to anything and has no backfill. Only updates should remain. `[unclear: which update action stays]` |
| 5 | Recommendation card | Going forward, the card must carry the applicant's residential address and phone number. |
| 6 | Deleted ToTs | Send the Reviewer the deleted records. |
| 7 | "Mother" on OSS recommendations | Some OSS recommendations show "Mother". The Developer is to check why. |
| 8 | Sidebar links to pages with tabs | Show a prompt asking which tab to open, before the user lands on the page. This applies to every sidebar link with tabs. |
| 9 | File commissioning | Comment out the radio buttons, which users say they do not need. |
| 10 | Loss of Documents | Add Loss of Documents for an OP, under OSS and under Land. See the full section below. |

### Loss of Documents: what was asked

- **Why it is needed:** a lost RofO or CofO already has a file number, but a lost OP does not, so one has to be generated when the loss is processed.
- **OSS:** add a Loss of Documents radio button.
- **Land:** add Loss of Documents as an option in the File Type dropdown. The Reviewer said it could be a radio button or file type, and described the dropdown; for now it is the only entry in that dropdown.
- **On selecting it:**
  1. The system automatically selects OP.
  2. The user selects the OP type (direct, for example).
  3. The user enters the OP details.
  4. The user commissions the file.
- **Starting point:** the process starts from Loss of Documents under Parcel Update, for both OSS and Land FC.
- **Confirmed after the session:**
  - Commissioning is done separately, as with the other parcel updates.
  - OSS and Land FC stay separate, under Parcel/Title Management (Post CofO).
  - If the OP is not in the system, the user has to go and capture it first.
- **Loss of Documents form:** the user first selects whether it is a regular file or an OP.
- **If it is an OP:**
  - The user enters the serial number.
  - If the OP already exists, the system fetches it into the form.
  - The system gives it a temporary number if it has none. If it already has one, the process continues.

---

## Cleaned conversation

### Backfill of phone number and address

**Reviewer:** The OSS session I did today. Apparently they have been suffering, because this backfilling has not been working. I keep asking you: why would we capture data in one place and then present it in another? There will be issues. Your implementation approach has killed us. What they do now is go and edit the record. Let me show you an example. Look at Update OP. Can you see my screen?

**Developer:** Yes, I'm seeing it.

**Reviewer:** When they were commissioning this file, they entered the phone number and applicant address, because it is even mandatory now. How come these details are missing? That is number one. And the applicant address is part of what shows on the RofO. Let me show you. This is on the OSS RofO. Can you see it?

**Developer:** Yes, I'm seeing it.

**Reviewer:** The address is missing here, just because of backfilling. These are the things that spoil this whole exercise, because they will say it's not working, it's not working. Meanwhile, this file number, this OSS RofO that I sent you: the RofO was deleted and the recommendation was deleted. `[unclear: "We guess so. Already said, rather, he said, what do you need? Because I use it for test."]`

### TP dropdown

**Reviewer:** That is number one, this backfilling here. Then number two. `[unclear: "I've seen that TP number 10"]` The TP dropdown was not showing before. I don't know why.

### Capture OP for Commissioned File: 2026 only

**Reviewer:** This file commissioning is for which year? If this is 2026, and we use this second button, Capture OP for Commissioned File, is it not supposed to load only 2026 files? Watch. I click this button, I come here to select a file number. Why am I able to select file numbers that are not 2026?

**Developer:** I'll check it. You said to restrict it to 2026 files only.

**Reviewer:** Am I supposed to tell you this, when we have two different interfaces, one for existing file records and one for new? Am I supposed to be the one to tell you this? That is number three.

### Disable "Edit this record"

**Reviewer:** Number four. Under the application, come to the options menu, to "Edit this record". We need to disable it. Disable the form, so that only updates remain. `[unclear: "only puts that updates, they can do updates right, updates will be"]` If you click on Edit this record, you will find all this residential address that we entered during `[unclear: "the DC"]`. There is no backfill here.

You just do something because they say you should do it. This form is not linked, so why would you even have it under Edit? We had this form before we moved to `[unclear: heard as "the phone", possibly the new form]`. You did not hook this residential address up to anything. There's no backfill. And this is what they have been using.

**Developer:** Yes, I understand.

### Recommendation card

**Reviewer:** Then under this recommendation, going forward, note the section for the residential address of the applicant, and that includes the applicant's phone number, in this card.

**Developer:** Okay, sir.

**Reviewer:** That is number five.

### Deleted ToTs

**Reviewer:** I could not but notice that these ToTs have been reduced to 700. What happened?

**Developer:** It was when I deleted the ToTs that had no OPs, 5,000 of them. Those were the exact numbers that we imported, the ones the ToT generator created. I took a backup.

**Reviewer:** Don't you think it's much?

**Developer:** We have 2,600 left. They were all generated. I confirmed before deleting.

**Reviewer:** How did you confirm 7,000 records?

**Developer:** It shows created by, imported through the ToT generator. I identified them where created by is ToT generated and there is no OP for that ToT.

**Reviewer:** Those 7,000, those 5,000 records, can you send them to me `[unclear: heard as "in these questions"]`?

**Developer:** Okay, I'll send them to you.

The figures above (700, 2,600, 5,000 and 7,000) are as spoken and do not agree with each other.

### "Mother" on OSS recommendations

**Reviewer:** Now this recommendation, under OSS. Some OSS recommendations have this "Mother". `[unclear: "because I can an OSSS recommendation with a new use before the house is announcing this model here"]` Can you explain this? Do you know how this Mother came about?

**Developer:** Let me check what happened.

**Reviewer:** Write it down, my friend.

**Developer:** I'm recording, sir.

**Reviewer:** I don't understand. If the recommendation was generated from OSS, and we don't have that luxury where they select `[unclear: heard as "the tablets"]`, why is it showing like this?

**Developer:** I have written it down. I will check.

*[Personal side conversation removed.]*

**Reviewer:** So there are `[unclear: heard as "insurance"]` for OSS?

**Developer:** No. I'm seeing this for the first time today.

**Reviewer:** Those are the corrections they noticed.

### Prompt on sidebar links with tabs

**Reviewer:** Let's go back to file commissioning, this thing we split into two types. When the user clicks this sidebar link, there should be a prompt: which one do you want to go to? Say FEFR, so they choose before going there. For all our sidebar links that have tabs, there should be a prompt, so that they don't have to click one first. Especially RofO. Maybe it is RofO Batch that I want to go to. If I first land on Not Printed before I can select, that defeats the purpose. You understand?

### File commissioning radio buttons

**Reviewer:** On this file commissioning, they said they don't need all these radio buttons. So comment them out.

### Loss of Documents

**Reviewer:** However, they said they treat Loss of Documents for OP. Before now I thought it was maybe a lost RofO or a lost CofO, which would have an existing file number.

**Developer:** Yes.

**Reviewer:** So they are not issuing or commissioning a file number for those. But if somebody misplaces their OP, when they process Loss of Documents for that person, they will have to generate a file number for them.

**Developer:** Okay.

**Reviewer:** So you have to put a Loss of Documents radio button under OSS. And I will show you where we are going to add it under Land: as a radio button here, or under File Type here. Under that dropdown, for now, it will be Loss of Documents. And then it must be an OP. Once it is triggered, it should default to OP. They will select the OP, and then they can give it the file number. They have to capture the whole OP details.

The same thing here under Land, under this File Type: Loss of Documents. Once the person selects it, the system will automatically select OP and tell them to select which OP. Which OP type: direct, or whatever it is called. Then they enter the OP details and commission the file.

**Developer:** Okay.

**Reviewer:** But the process will start from Loss of Documents under Parcel Update. When you click Loss of Documents, there will be two types. Let's check, there's a form here. Here you put the file number. If it is an OP, they will enter the serial number. If the OP already exists, or since we are doing TP number now `[unclear]`, the system will fetch it and bring it here. It will give it a temporary number if it does not have one. If it has one, it will continue.

What I'm saying is, when you click this Loss of Documents button, you have to select whether it is a regular file or an OP. You understand?

**Developer:** Yes, I understand.

### Close

**Reviewer:** Those are the corrections. Now let's go to Survey. `[unclear: "That's chicken number 10, alpha with it."]`
