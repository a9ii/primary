
# Iraq Exam Results Announcement Website

This is a simple web application that displays the exam results status for Iraqi directorates using Supabase as the backend database.

---

## Features

- Fetches and displays a list of Iraqi directorates with their exam result announcement status.
- Shows the latest announced results in a scrolling notification bar.
- Supports Arabic search with normalization for better matching.
- Links to Google Drive results or Telegram for updates.
- Responsive and modern UI built with Tailwind CSS and Arabic web fonts.

---

## Requirements

- PHP 7.4 or higher
- PHP cURL extension enabled
- Supabase project with PostgreSQL database

---

## Supabase Database Setup

You can create the necessary database schema and initial data by running the following SQL script in your Supabase SQL editor:

```sql
-- SQL Script for Supabase Database Setup
-- For Iraq Exam Results Website

-- 1. Function to update 'updated_at' timestamp automatically
CREATE OR REPLACE FUNCTION public.handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = timezone('utc'::text, now());
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 2. Create 'directorates' table
CREATE TABLE IF NOT EXISTS public.directorates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name_ar TEXT NOT NULL UNIQUE,
  name_en TEXT,
  is_results_announced BOOLEAN DEFAULT FALSE NOT NULL,
  drive_link TEXT,
  announced_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL,
  updated_at TIMESTAMPTZ DEFAULT timezone('utc'::text, now()) NOT NULL
);

COMMENT ON TABLE public.directorates IS 'Stores information about Iraqi education directorates and their exam result status.';

-- 3. Trigger to update 'updated_at' on row update
DROP TRIGGER IF EXISTS on_directorates_update ON public.directorates;
CREATE TRIGGER on_directorates_update
  BEFORE UPDATE ON public.directorates
  FOR EACH ROW
  EXECUTE PROCEDURE public.handle_updated_at();

-- 4. Insert initial directorates data
INSERT INTO public.directorates (name_ar, name_en) VALUES
  ('السليمانية', 'Sulaymaniyah'),
  ('إربيل', 'Erbil'),
  ('دهوك', 'Duhok'),
  ('الأنبار', 'Al Anbar'),
  ('البصرة', 'Basra'),
  ('الرصافة الأولى', 'Al-Rusafa 1st'),
  ('الرصافة الثانية', 'Al-Rusafa 2nd'),
  ('الرصافة الثالثة', 'Al-Rusafa 3rd'),
  ('القادسية', 'Al-Qadisiyyah'),
  ('الكرخ الأولى', 'Al-Karkh 1st'),
  ('الكرخ الثانية', 'Al-Karkh 2nd'),
  ('الكرخ الثالثة', 'Al-Karkh 3rd'),
  ('المثنى', 'Al Muthanna'),
  ('النجف الأشرف', 'Najaf'),
  ('بابل', 'Babil'),
  ('ديالى', 'Diyala'),
  ('ذي قار', 'Dhi Qar'),
  ('صلاح الدين', 'Salah ad Din'),
  ('كربلاء المقدسة', 'Karbala'),
  ('كركوك', 'Kirkuk'),
  ('ميسان', 'Maysan'),
  ('نينوى', 'Nineveh'),
  ('واسط', 'Wasit')
ON CONFLICT (name_ar) DO NOTHING;

-- 5. Enable Row Level Security and allow public read access
ALTER TABLE public.directorates ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Public can read directorates" ON public.directorates;
CREATE POLICY "Public can read directorates"
  ON public.directorates
  FOR SELECT
  USING (true);

-- 6. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_directorates_name_ar ON public.directorates (name_ar);
CREATE INDEX IF NOT EXISTS idx_directorates_announced_at_desc ON public.directorates (announced_at DESC NULLS LAST) WHERE is_results_announced = TRUE;
CREATE INDEX IF NOT EXISTS idx_directorates_is_results_announced ON public.directorates (is_results_announced);
````

---

## Usage Instructions

1. Create a Supabase project and run the above SQL script in the SQL editor to setup your database.
2. Update the PHP file with your Supabase URL and anon API key:

```php
$supabase_url = 'your_SUPABASE_URL_here';
$supabase_anon_key = 'your_SUPABASE_ANON_KEY_here';
```

3. Deploy the PHP files on a web server with PHP and cURL enabled.
4. Open the site in a browser to view the exam results status.

---

## Security Notes

* Do NOT commit your real Supabase anon key or URL to public repositories.
* Use environment variables or other secure methods to store API keys.
* If a key is exposed, immediately revoke it in the Supabase dashboard.

---

## Technologies Used

* PHP for backend data fetching.
* Supabase as backend database and REST API provider.
* Tailwind CSS for frontend styling.
* FontAwesome and Google Fonts for icons and Arabic typography.

---

## License

MIT License

---

## Contact

For questions or support, reach out on Telegram: [https://t.me/edu2iq](https://t.me/edu2iq)

---

Thank you for using this project! 🚀

```

Let me know if you want me to generate the actual README file for you to download or add anything else!
```
