# Video Format Guide for Learning Platform

## Problem: Black Video Screen

If your video shows a black screen, it's likely because the video codec is not compatible with web browsers.

---

## ✅ Browser-Compatible Video Specifications

### **Required Format:**
- **Container:** MP4 (.mp4)
- **Video Codec:** H.264 (AVC)
- **Audio Codec:** AAC
- **Recommended Resolution:** 1280x720 (720p HD)
- **Recommended Bitrate:** 2-5 Mbps

---

## 🔧 How to Convert Videos

### **Option 1: Using HandBrake (FREE - Recommended)**

1. **Download HandBrake:**
   - Visit: https://handbrake.fr/downloads.php
   - Download and install

2. **Convert Your Video:**
   - Open HandBrake
   - Click "Open Source" → Select your video file
   - **Preset:** Choose "Web" → "Gmail Large 3 Minutes 720p30"
   - **Format:** MP4
   - **Web Optimized:** ✅ Check this box (IMPORTANT!)
   - Click "Start Encode"

3. **Upload the converted video** to your learning platform

---

### **Option 2: Using FFmpeg (Command Line)**

If you have FFmpeg installed:

```bash
ffmpeg -i input_video.mp4 -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 128k -movflags +faststart output_video.mp4
```

**Parameters explained:**
- `-c:v libx264` = H.264 video codec
- `-preset medium` = Encoding speed/quality balance
- `-crf 23` = Quality (18-28, lower = better quality)
- `-c:a aac` = AAC audio codec
- `-movflags +faststart` = Web optimization (allows video to start playing before fully downloaded)

---

### **Option 3: Online Converter (Quick)**

1. Visit: https://www.freeconvert.com/video-converter
2. Upload your video
3. Select: **Convert to MP4**
4. Click "Advanced Settings":
   - Video Codec: **H.264**
   - Audio Codec: **AAC**
   - Resolution: **1280x720**
5. Click "Convert"
6. Download and upload to platform

---

## 📋 Checklist Before Uploading

Before uploading a video to the learning platform:

- [ ] Video is in **.mp4** format
- [ ] Video codec is **H.264** (not H.265/HEVC)
- [ ] Audio codec is **AAC** (not AC3, DTS, or others)
- [ ] Video is **web-optimized** (faststart enabled)
- [ ] File size is reasonable (< 100MB recommended for smooth streaming)
- [ ] Resolution is **1280x720** or **1920x1080**
- [ ] Video plays correctly in VLC or browser before uploading

---

## 🔍 How to Check Your Current Video Format

### **Using VLC Media Player:**
1. Open video in VLC
2. Go to: **Tools** → **Codec Information** (Ctrl+J)
3. Check:
   - **Codec:** Should say "H264 - MPEG-4 AVC"
   - **Audio Codec:** Should say "MPEG AAC Audio"

### **Using MediaInfo (Professional):**
1. Download: https://mediaarea.net/en/MediaInfo
2. Open your video file
3. Look for:
   - **Format:** MP4
   - **Video Codec:** AVC (H.264)
   - **Audio Codec:** AAC

---

## 🚫 Common Incompatible Formats

**These will show black screen in browsers:**

❌ **H.265/HEVC** - Not widely supported
❌ **VP9** - Limited browser support
❌ **AV1** - Too new, limited support
❌ **WMV** - Windows Media Video (not web compatible)
❌ **AVI** - Old container format
❌ **MKV** - Not supported in HTML5 video
❌ **FLV** - Flash video (obsolete)

---

## 💡 Quick Test

After converting your video, test it by:

1. Opening it directly in **Chrome browser** (drag & drop into browser window)
2. If it plays in Chrome, it will work on the platform
3. If it shows black screen in Chrome, reconvert with correct settings

---

## 📺 Recommended Settings for Training Videos

### **For Best Quality:**
- Resolution: **1920x1080** (Full HD)
- Video Bitrate: **4-5 Mbps**
- Audio Bitrate: **192 kbps**

### **For Smaller File Size:**
- Resolution: **1280x720** (HD)
- Video Bitrate: **2-3 Mbps**
- Audio Bitrate: **128 kbps**

### **For Fastest Upload/Streaming:**
- Resolution: **854x480** (SD)
- Video Bitrate: **1-2 Mbps**
- Audio Bitrate: **96 kbps**

---

## 🎬 HandBrake Quick Settings (Recommended)

**Profile Name:** Web Optimized Training Video

1. **Summary Tab:**
   - Format: MP4
   - ✅ Web Optimized

2. **Video Tab:**
   - Video Codec: H.264 (x264)
   - Framerate: Same as source
   - Quality: Constant Quality 23

3. **Audio Tab:**
   - Codec: AAC
   - Bitrate: 128 or 160
   - Samplerate: Auto

4. **Save preset** for future use!

---

## 🔄 Re-uploading Fixed Video

After converting your video:

1. Login to **Admin Panel**
2. Go to **Manage Modules**
3. Click **Edit** on the module
4. Under "Video Management" → Click **Replace Video**
5. Upload your converted **.mp4** file
6. Save changes
7. Test by viewing the module

---

## ❓ Still Having Issues?

If video still shows black screen after conversion:

1. **Check file permissions** - Make sure uploads/videos/ folder is writable
2. **Check file size** - Very large files (>500MB) may timeout
3. **Check server limits** - PHP upload_max_filesize and post_max_size
4. **Clear browser cache** - Old cached video might be loading
5. **Try different browser** - Test in Chrome, Firefox, Edge

---

**Created:** October 28, 2025  
**Platform Version:** 6.0
