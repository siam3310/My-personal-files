"use client";

import React, { useState } from "react";

const WatchLive = () => {
  const [activeTab, setActiveTab] = useState(1);

  const handleTabClick = (tabNumber) => {
    setActiveTab(tabNumber);
  };

  return (
    <div className="w-full text-black">
      {/* LOVE TO ALL */}
      <div className="w-full bg-gray-800 text-center py-2 font-bold text-lg text-white capitalize">
        LOVE TO ALL
      </div>

      {/* Player  */}
      <div className="flex justify-center mt-0">
        {activeTab === 1 && (
          <iframe
            src="https://cinebuzz.netfy.live/crichd/play.php?cricid=willowusa"
            width="90%"
            height="540"
            frameBorder="0"
            allowFullScreen={true}
            className="aspect-video object-fill w-[100%] h-[56.25%] sm:w-[80%] lg:w-[70%] xl:w-[60%]"
          ></iframe>
        )}
        {activeTab === 2 && (
          <iframe
            src="https://cinebuzz.netfy.live/crichd/play.php?cricid=skyscric"
            width="90%"
            height="540"
            frameBorder="0"
            allowFullScreen={true}
            className="aspect-video object-fill w-[100%] h-[56.25%] sm:w-[80%] lg:w-[70%] xl:w-[60%]"
          ></iframe>
        )}
        {activeTab === 3 && (
          <iframe
            src="https://cinebuzz.netfy.live/crichd/play.php?cricid=supercricket"
            width="90%"
            height="540"
            frameBorder="0"
            allowFullScreen={true}
            className="aspect-video object-fill w-[100%] h-[56.25%] sm:w-[80%] lg:w-[70%] xl:w-[60%]"
          ></iframe>
        )}
        {activeTab === 4 && (
          <iframe
            src="https://cinebuzz.netfy.live/crichd/play.php?cricid=tenspk"
            width="90%"
            height="540"
            frameBorder="0"
            allowFullScreen={true}
            className="aspect-video object-fill w-[100%] h-[56.25%] sm:w-[80%] lg:w-[70%] xl:w-[60%]"
          ></iframe>
        )}
      </div>

      {/* Bookmark It */}
      <div className="w-full bg-gray-800 text-center py-2 font-bold text-lg text-white capitalize mt-0">
        Bookmark It👇 to Stay With Us
      </div>

      {/* BPL Link */}
      <div className="w-full bg-gray-800 text-center py-2 font-bold text-lg text-white capitalize mt-0">
        BPL25.NETLIFY.APP
      </div>

      {/* Buttons Container */}
      <div className="flex justify-center mt-4 px-4">
        <div className="bg-gray-800 p-6 rounded-xl w-full sm:w-[98%] lg:w-[70%] xl:w-[60%]">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {["Willow", "Sky Sports Cricket", "Super Sports Cricket", "Ten Cricket"].map(
              (label, index) => (
                <button
                  key={index}
                  className={`w-full py-3 font-bold text-lg rounded-lg transition-all duration-300 ${
                    activeTab === index + 1
                      ? "bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg transform scale-105"
                      : "bg-white text-gray-700 border-2 border-gray-400 hover:bg-gray-200"
                  }`}
                  onClick={() => handleTabClick(index + 1)}
                >
                  {label}
                </button>
              )
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default WatchLive;
